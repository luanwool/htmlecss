package br.com.radardinamico.service

import android.annotation.SuppressLint
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.IBinder
import android.os.Looper
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import br.com.radardinamico.data.AlertEngine
import br.com.radardinamico.data.GeoPoint
import br.com.radardinamico.data.Insights
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeEvent
import br.com.radardinamico.data.SurgeKind
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.data.SurgeSource
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.util.Calendar

/**
 * Serviço em primeiro plano: acompanha o GPS, busca dinâmicos da comunidade,
 * avalia as regras de alerta e envia notificações/sugestões.
 */
class RadarService : Service(), LocationListener {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Default)
    private val lastAlertByCell = mutableMapOf<String, Long>()
    private var lastSuggestionAt = 0L
    private var lastSuggestedCell: String? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> { stopSelf(); return START_NOT_STICKY }
            ACTION_QUICK_MARK -> {
                val value = SettingsStore.state.value.minMoney
                markHere(this, SurgeKind.MONEY, value)
                return START_STICKY
            }
        }
        if (RadarState.running.value) return START_STICKY
        ServiceCompat.startForeground(
            this, Notifier.ID_SERVICE,
            Notifier.serviceNotification(this, "Procurando dinâmicos…"),
            if (Build.VERSION.SDK_INT >= 29) ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION else 0,
        )
        RadarState.running.value = true
        startLocation()
        startLoops()
        return START_STICKY
    }

    @SuppressLint("MissingPermission")
    private fun startLocation() {
        val lm = getSystemService(LocationManager::class.java)
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER).forEach { p ->
            if (lm.isProviderEnabled(p)) {
                runCatching { lm.requestLocationUpdates(p, 10_000L, 50f, this, Looper.getMainLooper()) }
                runCatching { lm.getLastKnownLocation(p)?.let { onLocationChanged(it) } }
            }
        }
    }

    override fun onLocationChanged(location: Location) {
        RadarState.location.value = GeoPoint(location.latitude, location.longitude)
    }

    private fun startLoops() {
        val repo = SurgeRepository.get(this)
        // Reavalia sempre que chega uma leitura nova (tela, comunidade ou manual)
        scope.launch { repo.recent.collectLatest { evaluate() } }
        // Loop periódico: comunidade + reavaliação por deslocamento + sugestões
        scope.launch {
            while (isActive) {
                val s = SettingsStore.state.value
                val here = RadarState.location.value
                if (s.sourceCommunity && s.communityUrl.isNotBlank() && here != null) {
                    runCatching { CommunitySource.fetch(s.communityUrl, here, s.radiusKm * 2) }
                        .onSuccess { list ->
                            list.filterNot { repo.isDuplicate(it) }.forEach { repo.insert(it) }
                            RadarState.lastCommunitySync.value = "${list.size} leituras recebidas"
                        }
                        .onFailure { RadarState.lastCommunitySync.value = "Falha: ${it.message}" }
                }
                evaluate()
                maybeSuggest()
                delay(s.pollSeconds.coerceAtLeast(15) * 1000L)
            }
        }
    }

    private fun evaluate() {
        val s = SettingsStore.state.value
        val repo = SurgeRepository.get(this)
        val now = System.currentTimeMillis()
        val match = AlertEngine.evaluate(
            s, repo.recent.value, RadarState.location.value, now, Calendar.getInstance(), lastAlertByCell,
        )
        if (match != null) {
            lastAlertByCell[match.cell] = now
            Notifier.alert(this, s, match)
        }
        val active = repo.recent.value.count { now - it.timestamp <= s.freshnessMin * 60_000L }
        updateOngoing("$active leituras recentes • alerta a partir de +R$ ${s.minMoney.toInt()} em ${s.radiusKm.toInt()} km")
    }

    private fun maybeSuggest() {
        val s = SettingsStore.state.value
        if (!s.suggestionsEnabled || !s.alertsEnabled) return
        val now = System.currentTimeMillis()
        if (now - lastSuggestionAt < s.suggestionIntervalMin * 60_000L) return
        if (!AlertEngine.withinSchedule(s, Calendar.getInstance())) return
        val here = RadarState.location.value ?: return
        val history = SurgeRepository.get(this).since(now - 60L * 24 * 3600_000L)
        val best = Insights.bestSpots(history, Calendar.getInstance(), here, maxDistanceKm = s.radiusKm * 3, limit = 1)
            .firstOrNull { it.samples >= 3 && (it.distanceKm ?: 0.0) > 0.8 } ?: return
        if (best.cell == lastSuggestedCell) return
        lastSuggestionAt = now
        lastSuggestedCell = best.cell
        Notifier.suggestion(this, s, best)
    }

    private fun updateOngoing(text: String) {
        if (!Notifier.hasPostPermission(this)) return
        runCatching {
            androidx.core.app.NotificationManagerCompat.from(this)
                .notify(Notifier.ID_SERVICE, Notifier.serviceNotification(this, text))
        }
    }

    override fun onDestroy() {
        getSystemService(LocationManager::class.java).removeUpdates(this)
        scope.cancel()
        RadarState.running.value = false
        super.onDestroy()
    }

    companion object {
        const val ACTION_STOP = "br.com.radardinamico.STOP"
        const val ACTION_QUICK_MARK = "br.com.radardinamico.QUICK_MARK"

        fun start(ctx: Context) =
            ContextCompat.startForegroundService(ctx, Intent(ctx, RadarService::class.java))

        fun stop(ctx: Context) = ctx.stopService(Intent(ctx, RadarService::class.java))

        /** Registra um dinâmico na posição atual (botão "Marcar aqui"). */
        fun markHere(ctx: Context, kind: SurgeKind, value: Double): Boolean {
            val here = RadarState.location.value ?: return false
            val e = SurgeRepository.get(ctx).insert(
                SurgeEvent(timestamp = System.currentTimeMillis(), lat = here.lat, lng = here.lng,
                    kind = kind, value = value, source = SurgeSource.MANUAL)
            )
            shareIfEnabled(e)
            return true
        }

        fun shareIfEnabled(e: SurgeEvent) {
            val s = SettingsStore.state.value
            if (!s.shareToCommunity || s.communityUrl.isBlank()) return
            Thread { runCatching { CommunitySource.publish(s.communityUrl, e) } }.start()
        }
    }
}
