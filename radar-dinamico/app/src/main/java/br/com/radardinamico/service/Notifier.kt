package br.com.radardinamico.service

import android.annotation.SuppressLint
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.speech.tts.TextToSpeech
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import br.com.radardinamico.MainActivity
import br.com.radardinamico.R
import br.com.radardinamico.data.AlertMatch
import br.com.radardinamico.data.Geo
import br.com.radardinamico.data.HotSpot
import br.com.radardinamico.data.Settings
import java.util.Locale

object Notifier {
    const val CH_SERVICE = "radar_service"
    const val CH_ALERT_SOUND = "radar_alert_sound"
    const val CH_ALERT_SILENT = "radar_alert_silent"
    const val CH_SUGGESTION = "radar_suggestion"
    const val ID_SERVICE = 1

    private var tts: TextToSpeech? = null

    fun createChannels(ctx: Context) {
        val nm = ctx.getSystemService(NotificationManager::class.java)
        nm.createNotificationChannels(listOf(
            NotificationChannel(CH_SERVICE, "Radar ativo", NotificationManager.IMPORTANCE_LOW),
            NotificationChannel(CH_ALERT_SOUND, "Alertas de dinâmico (com som)", NotificationManager.IMPORTANCE_HIGH)
                .apply { enableVibration(false) },
            NotificationChannel(CH_ALERT_SILENT, "Alertas de dinâmico (silencioso)", NotificationManager.IMPORTANCE_HIGH)
                .apply { setSound(null, null); enableVibration(false) },
            NotificationChannel(CH_SUGGESTION, "Sugestões de regiões", NotificationManager.IMPORTANCE_DEFAULT),
        ))
    }

    fun openAppIntent(ctx: Context): PendingIntent = PendingIntent.getActivity(
        ctx, 0, Intent(ctx, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP),
        PendingIntent.FLAG_IMMUTABLE,
    )

    private fun navigateIntent(ctx: Context, lat: Double, lng: Double, code: Int): PendingIntent {
        val uri = Uri.parse("geo:$lat,$lng?q=$lat,$lng(Dinâmico)")
        return PendingIntent.getActivity(
            ctx, code, Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            PendingIntent.FLAG_IMMUTABLE,
        )
    }

    fun serviceNotification(ctx: Context, text: String) =
        NotificationCompat.Builder(ctx, CH_SERVICE)
            .setSmallIcon(R.drawable.ic_stat_radar)
            .setContentTitle("Radar de Dinâmico ativo")
            .setContentText(text)
            .setOngoing(true)
            .setContentIntent(openAppIntent(ctx))
            .addAction(0, "Marcar dinâmico aqui", PendingIntent.getService(
                ctx, 10, Intent(ctx, RadarService::class.java).setAction(RadarService.ACTION_QUICK_MARK),
                PendingIntent.FLAG_IMMUTABLE,
            ))
            .addAction(0, "Parar", PendingIntent.getService(
                ctx, 11, Intent(ctx, RadarService::class.java).setAction(RadarService.ACTION_STOP),
                PendingIntent.FLAG_IMMUTABLE,
            ))
            .build()

    @SuppressLint("MissingPermission")
    fun alert(ctx: Context, s: Settings, m: AlertMatch) {
        val e = m.event
        val dist = m.distanceKm?.let { Geo.formatKm(it) }
        val dir = RadarState.location.value?.let { Geo.cardinal(it, br.com.radardinamico.data.GeoPoint(e.lat, e.lng)) }
        val title = "Dinâmico ${e.label()}" + (dist?.let { " a $it" } ?: "")
        val body = buildString {
            append(e.source.label)
            dir?.let { append(" • direção $it") }
            append(" • raio ${s.radiusKm.toInt()} km")
        }
        val n = NotificationCompat.Builder(ctx, if (s.sound) CH_ALERT_SOUND else CH_ALERT_SILENT)
            .setSmallIcon(R.drawable.ic_stat_radar)
            .setContentTitle(title)
            .setContentText(body)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_NAVIGATION)
            .setAutoCancel(true)
            .setContentIntent(openAppIntent(ctx))
            .addAction(0, "Navegar até lá", navigateIntent(ctx, e.lat, e.lng, e.id.toInt()))
            .build()
        NotificationManagerCompat.from(ctx).notify(1000 + (e.id % 500).toInt(), n)
        if (s.vibrate) vibrate(ctx)
        if (s.speak) speak(ctx, buildString {
            append("Dinâmico de ")
            append(spokenValue(e))
            m.distanceKm?.let { append(", a ${spokenKm(it)}") }
        })
    }

    @SuppressLint("MissingPermission")
    fun suggestion(ctx: Context, s: Settings, spot: HotSpot) {
        val value = spot.avgMoney?.let { "+R$ %.0f em média".format(it) }
            ?: spot.avgMultiplier?.let { "%.1fx em média".format(it).replace('.', ',') } ?: return
        val dist = spot.distanceKm?.let { " a ${Geo.formatKm(it)}" } ?: ""
        val n = NotificationCompat.Builder(ctx, CH_SUGGESTION)
            .setSmallIcon(R.drawable.ic_stat_radar)
            .setContentTitle("Região boa neste horário$dist")
            .setContentText("Histórico: $value (${spot.samples} leituras)")
            .setAutoCancel(true)
            .setContentIntent(openAppIntent(ctx))
            .addAction(0, "Navegar até lá", navigateIntent(ctx, spot.center.lat, spot.center.lng, 900))
            .build()
        NotificationManagerCompat.from(ctx).notify(900, n)
        if (s.speak) speak(ctx, "Sugestão: região com $value$dist")
    }

    private fun spokenValue(e: br.com.radardinamico.data.SurgeEvent) = when (e.kind) {
        br.com.radardinamico.data.SurgeKind.MONEY ->
            if (e.value % 1.0 == 0.0) "${e.value.toInt()} reais" else "%.2f reais".format(e.value).replace('.', ',')
        br.com.radardinamico.data.SurgeKind.MULTIPLIER -> "%.1f vezes".format(e.value).replace('.', ',')
    }

    private fun spokenKm(km: Double) =
        if (km < 1) "${(km * 1000).toInt()} metros" else "%.1f quilômetros".format(km).replace('.', ',')

    private fun vibrate(ctx: Context) {
        val v = ctx.getSystemService(Vibrator::class.java) ?: return
        v.vibrate(VibrationEffect.createWaveform(longArrayOf(0, 250, 120, 250), -1))
    }

    private fun speak(ctx: Context, text: String) {
        val existing = tts
        if (existing != null) {
            existing.speak(text, TextToSpeech.QUEUE_ADD, null, "radar")
            return
        }
        tts = TextToSpeech(ctx.applicationContext) { status ->
            if (status == TextToSpeech.SUCCESS) {
                tts?.language = Locale("pt", "BR")
                tts?.speak(text, TextToSpeech.QUEUE_ADD, null, "radar")
            }
        }
    }

    fun hasPostPermission(ctx: Context) =
        Build.VERSION.SDK_INT < 33 || NotificationManagerCompat.from(ctx).areNotificationsEnabled()
}
