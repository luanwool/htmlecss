package br.com.radardinamico.data

import android.content.Context
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/** Todas as preferências do motorista. Cada campo é editável na tela de Configurações. */
data class Settings(
    // Alertas
    val alertsEnabled: Boolean = true,
    val minMoney: Double = 6.0,            // dinâmico mínimo em R$ (ex.: +6)
    val useMultiplier: Boolean = true,     // também alertar por multiplicador
    val minMultiplier: Double = 1.5,
    val radiusKm: Double = 5.0,
    val freshnessMin: Int = 15,            // por quanto tempo uma leitura é considerada válida
    val cooldownMin: Int = 5,              // intervalo mínimo entre alertas da mesma região
    // Horário
    val scheduleEnabled: Boolean = false,
    val startHour: Int = 6,
    val endHour: Int = 23,
    val activeDays: Set<Int> = setOf(1, 2, 3, 4, 5, 6, 7), // 1=Dom ... 7=Sáb (Calendar)
    // Forma do alerta
    val sound: Boolean = true,
    val vibrate: Boolean = true,
    val speak: Boolean = false,
    val showNeighborhood: Boolean = true,  // bairro em destaque no título da notificação            // ler o alerta em voz alta (bom para quem está dirigindo)
    // Sugestões a partir do histórico
    val suggestionsEnabled: Boolean = true,
    val suggestionIntervalMin: Int = 30,
    // Fontes de dados
    val sourceUberScreen: Boolean = true,
    val sourceUberNotifications: Boolean = true, // ler notificações do Uber Driver (ex.: "alta demanda")
    // Calibração do leitor de tela (posição do rótulo no mapa -> coordenada)
    val mapZoom: Double = 14.0,
    val carYPercent: Int = 45,
    val sourceCommunity: Boolean = false,
    val communityUrl: String = "",
    val shareToCommunity: Boolean = false,
    val pollSeconds: Int = 60,
)

object SettingsStore {
    private const val PREFS = "radar_settings"
    private val _state = MutableStateFlow(Settings())
    val state: StateFlow<Settings> = _state
    private var loaded = false

    fun init(ctx: Context) {
        if (loaded) return
        loaded = true
        val p = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val d = Settings()
        _state.value = Settings(
            alertsEnabled = p.getBoolean("alertsEnabled", d.alertsEnabled),
            minMoney = p.getFloat("minMoney", d.minMoney.toFloat()).toDouble(),
            useMultiplier = p.getBoolean("useMultiplier", d.useMultiplier),
            minMultiplier = p.getFloat("minMultiplier", d.minMultiplier.toFloat()).toDouble(),
            radiusKm = p.getFloat("radiusKm", d.radiusKm.toFloat()).toDouble(),
            freshnessMin = p.getInt("freshnessMin", d.freshnessMin),
            cooldownMin = p.getInt("cooldownMin", d.cooldownMin),
            scheduleEnabled = p.getBoolean("scheduleEnabled", d.scheduleEnabled),
            startHour = p.getInt("startHour", d.startHour),
            endHour = p.getInt("endHour", d.endHour),
            activeDays = p.getStringSet("activeDays", null)?.mapNotNull { it.toIntOrNull() }?.toSet() ?: d.activeDays,
            sound = p.getBoolean("sound", d.sound),
            vibrate = p.getBoolean("vibrate", d.vibrate),
            speak = p.getBoolean("speak", d.speak),
            showNeighborhood = p.getBoolean("showNeighborhood", d.showNeighborhood),
            suggestionsEnabled = p.getBoolean("suggestionsEnabled", d.suggestionsEnabled),
            suggestionIntervalMin = p.getInt("suggestionIntervalMin", d.suggestionIntervalMin),
            sourceUberScreen = p.getBoolean("sourceUberScreen", d.sourceUberScreen),
            sourceUberNotifications = p.getBoolean("sourceUberNotifications", d.sourceUberNotifications),
            mapZoom = p.getFloat("mapZoom", d.mapZoom.toFloat()).toDouble(),
            carYPercent = p.getInt("carYPercent", d.carYPercent),
            sourceCommunity = p.getBoolean("sourceCommunity", d.sourceCommunity),
            communityUrl = p.getString("communityUrl", d.communityUrl) ?: "",
            shareToCommunity = p.getBoolean("shareToCommunity", d.shareToCommunity),
            pollSeconds = p.getInt("pollSeconds", d.pollSeconds),
        )
    }

    fun update(ctx: Context, transform: (Settings) -> Settings) {
        val s = transform(_state.value)
        _state.value = s
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
            .putBoolean("alertsEnabled", s.alertsEnabled)
            .putFloat("minMoney", s.minMoney.toFloat())
            .putBoolean("useMultiplier", s.useMultiplier)
            .putFloat("minMultiplier", s.minMultiplier.toFloat())
            .putFloat("radiusKm", s.radiusKm.toFloat())
            .putInt("freshnessMin", s.freshnessMin)
            .putInt("cooldownMin", s.cooldownMin)
            .putBoolean("scheduleEnabled", s.scheduleEnabled)
            .putInt("startHour", s.startHour)
            .putInt("endHour", s.endHour)
            .putStringSet("activeDays", s.activeDays.map { it.toString() }.toSet())
            .putBoolean("sound", s.sound)
            .putBoolean("vibrate", s.vibrate)
            .putBoolean("speak", s.speak)
            .putBoolean("showNeighborhood", s.showNeighborhood)
            .putBoolean("suggestionsEnabled", s.suggestionsEnabled)
            .putInt("suggestionIntervalMin", s.suggestionIntervalMin)
            .putBoolean("sourceUberScreen", s.sourceUberScreen)
            .putBoolean("sourceUberNotifications", s.sourceUberNotifications)
            .putFloat("mapZoom", s.mapZoom.toFloat())
            .putInt("carYPercent", s.carYPercent)
            .putBoolean("sourceCommunity", s.sourceCommunity)
            .putString("communityUrl", s.communityUrl)
            .putBoolean("shareToCommunity", s.shareToCommunity)
            .putInt("pollSeconds", s.pollSeconds)
            .apply()
    }
}
