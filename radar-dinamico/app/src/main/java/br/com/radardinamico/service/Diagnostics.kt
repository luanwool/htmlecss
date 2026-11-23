package br.com.radardinamico.service

import android.annotation.SuppressLint
import android.content.Context
import android.location.LocationManager
import android.os.Build
import br.com.radardinamico.data.GeoPoint
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/** Registro de erros + preferências de execução, para o app se recuperar sozinho e mostrar o que deu errado. */
object Diagnostics {
    private const val PREFS = "diagnostico"
    val processStartedAt = System.currentTimeMillis()

    fun installCrashHandler(ctx: Context) {
        val app = ctx.applicationContext
        val previous = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { t, e ->
            runCatching { save(app, "TRAVAMENTO (${t.name})", e, commit = true) }
            previous?.uncaughtException(t, e)
        }
    }

    fun error(ctx: Context, where: String, e: Throwable) = runCatching { save(ctx, where, e, commit = false) }

    private fun save(ctx: Context, where: String, e: Throwable, commit: Boolean) {
        val stamp = SimpleDateFormat("dd/MM HH:mm:ss", Locale("pt", "BR")).format(Date())
        val trace = e.stackTraceToString().lineSequence().take(14).joinToString("\n")
        val ed = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
            .putString("lastError", "$stamp • $where\n$trace")
        if (commit) ed.commit() else ed.apply()
    }

    fun lastError(ctx: Context): String? = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString("lastError", null)

    fun clearError(ctx: Context) = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().remove("lastError").apply()

    /** O motorista quer o radar ligado? (sobrevive a atualizações do app e reinícios) */
    fun radarWanted(ctx: Context) = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean("radarWanted", false)

    fun setRadarWanted(ctx: Context, v: Boolean) =
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean("radarWanted", v).apply()

    /** Última posição conhecida pelo Android, usada quando o radar ainda não recebeu GPS. */
    @SuppressLint("MissingPermission")
    fun lastKnownLocation(ctx: Context): GeoPoint? = runCatching {
        val lm = ctx.getSystemService(LocationManager::class.java)
        lm.getProviders(true)
            .mapNotNull { p -> runCatching { lm.getLastKnownLocation(p) }.getOrNull() }
            .maxByOrNull { it.time }
            ?.let { GeoPoint(it.latitude, it.longitude) }
    }.getOrNull()

    fun deviceInfo(ctx: Context): String {
        val v = runCatching { ctx.packageManager.getPackageInfo(ctx.packageName, 0).versionName }.getOrNull()
        return "Radar Dinâmico $v • Android ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT}) • ${Build.MANUFACTURER} ${Build.MODEL}"
    }
}
