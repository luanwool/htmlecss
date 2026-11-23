package br.com.radardinamico.service

import br.com.radardinamico.data.GeoPoint
import kotlinx.coroutines.flow.MutableStateFlow

/** Estado compartilhado entre serviço, leitor de tela e interface. */
object RadarState {
    val location = MutableStateFlow<GeoPoint?>(null)
    val running = MutableStateFlow(false)
    val lastScreenReadAt = MutableStateFlow<Long?>(null)
    val lastCommunitySync = MutableStateFlow<String?>(null)
}
