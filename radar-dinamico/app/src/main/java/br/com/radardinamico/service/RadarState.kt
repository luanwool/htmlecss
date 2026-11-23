package br.com.radardinamico.service

import br.com.radardinamico.data.GeoPoint
import kotlinx.coroutines.flow.MutableStateFlow

/** Estado compartilhado entre serviço, leitor de tela e interface. */
object RadarState {
    val location = MutableStateFlow<GeoPoint?>(null)
    val running = MutableStateFlow(false)
    val lastScreenReadAt = MutableStateFlow<Long?>(null)
    val lastCommunitySync = MutableStateFlow<String?>(null)
    /** Diagnóstico do leitor: o que foi lido na última varredura e onde (para calibrar). */
    val readerDebug = MutableStateFlow<List<String>>(emptyList())
    val lastUberNotification = MutableStateFlow<String?>(null)
    /** Quando o Android conectou o leitor de tela neste processo (null = não está rodando). */
    val readerConnectedAt = MutableStateFlow<Long?>(null)
    /** Situação atual do leitor, em texto, para a tela de status. */
    val readerStatus = MutableStateFlow<String?>(null)
}
