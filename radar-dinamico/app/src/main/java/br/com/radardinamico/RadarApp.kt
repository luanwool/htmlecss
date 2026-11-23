package br.com.radardinamico

import android.app.Application
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.service.Notifier
import org.osmdroid.config.Configuration

class RadarApp : Application() {
    override fun onCreate() {
        super.onCreate()
        br.com.radardinamico.service.Diagnostics.installCrashHandler(this)
        SettingsStore.init(this)
        Notifier.createChannels(this)
        Configuration.getInstance().userAgentValue = packageName
    }
}
