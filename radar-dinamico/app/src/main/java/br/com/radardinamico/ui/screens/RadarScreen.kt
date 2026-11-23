package br.com.radardinamico.ui.screens

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.provider.Settings as AndroidSettings
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import br.com.radardinamico.data.Geo
import br.com.radardinamico.data.GeoPoint
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeKind
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.service.RadarService
import br.com.radardinamico.service.Neighborhood
import br.com.radardinamico.service.RadarState
import br.com.radardinamico.ui.theme.RadarColors
import kotlinx.coroutines.delay

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun RadarScreen(modifier: Modifier, onOpenSettings: () -> Unit) {
    val ctx = LocalContext.current
    val settings by SettingsStore.state.collectAsState()
    val running by RadarState.running.collectAsState()
    val here by RadarState.location.collectAsState()
    val lastRead by RadarState.lastScreenReadAt.collectAsState()
    val community by RadarState.lastCommunitySync.collectAsState()
    val recent by SurgeRepository.get(ctx).recent.collectAsState()

    var tick by remember { mutableLongStateOf(System.currentTimeMillis()) }
    var permTick by remember { mutableIntStateOf(0) }
    LaunchedEffect(Unit) { while (true) { delay(5_000); tick = System.currentTimeMillis(); permTick++ } }

    fun granted(p: String) = ContextCompat.checkSelfPermission(ctx, p) == PackageManager.PERMISSION_GRANTED
    val hasLocation = remember(permTick) { granted(Manifest.permission.ACCESS_FINE_LOCATION) }
    val hasNotif = remember(permTick) {
        Build.VERSION.SDK_INT < 33 || granted(Manifest.permission.POST_NOTIFICATIONS)
    }
    val readerOn = remember(permTick) { isReaderEnabled(ctx) }
    val notifReaderOn = remember(permTick) { isNotificationReaderEnabled(ctx) }
    val uberNotif by RadarState.lastUberNotification.collectAsState()
    val readerConnectedAt by RadarState.readerConnectedAt.collectAsState()
    val readerStatus by RadarState.readerStatus.collectAsState()

    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        permTick++
        if (it[Manifest.permission.ACCESS_FINE_LOCATION] == true) RadarService.start(ctx)
    }
    fun requestAndStart() {
        val perms = buildList {
            add(Manifest.permission.ACCESS_FINE_LOCATION)
            add(Manifest.permission.ACCESS_COARSE_LOCATION)
            if (Build.VERSION.SDK_INT >= 33) add(Manifest.permission.POST_NOTIFICATIONS)
        }
        if (perms.all(::granted)) RadarService.start(ctx) else launcher.launch(perms.toTypedArray())
    }

    val fresh = recent
        .filter { tick - it.timestamp <= settings.freshnessMin * 60_000L }
        .map { e -> e to here?.let { Geo.distanceKm(it, GeoPoint(e.lat, e.lng)) } }
        .sortedWith(compareByDescending<Pair<br.com.radardinamico.data.SurgeEvent, Double?>> {
            br.com.radardinamico.data.AlertEngine.normalized(it.first)
        }.thenBy { it.second ?: 0.0 })

    var customDialog by remember { mutableStateOf(false) }

    LazyColumn(
        modifier.fillMaxSize().padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Spacer(Modifier.height(12.dp))
            Text("Radar de Dinâmico", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        }

        // Botão principal
        item {
            Button(
                onClick = { if (running) RadarService.stop(ctx) else requestAndStart() },
                modifier = Modifier.fillMaxWidth().height(72.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (running) RadarColors.Danger else RadarColors.Good,
                    contentColor = androidx.compose.ui.graphics.Color.White,
                ),
            ) {
                Icon(if (running) Icons.Filled.Stop else Icons.Filled.PlayArrow, null, Modifier.size(28.dp))
                Spacer(Modifier.width(8.dp))
                Text(if (running) "Parar radar" else "Iniciar radar", fontSize = 20.sp, fontWeight = FontWeight.Bold)
            }
        }

        // Regra atual
        item {
            SectionCard(modifier = Modifier.clickable(onClick = onOpenSettings)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text("Sua regra de alerta", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(
                            buildString {
                                append("A partir de +R$ ${settings.minMoney.toInt()}")
                                if (settings.useMultiplier) append(" ou ${"%.1f".format(settings.minMultiplier).replace('.', ',')}x")
                                append(" em até ${settings.radiusKm.toInt()} km")
                            },
                            style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold,
                        )
                        if (!settings.alertsEnabled) Text("Alertas desligados", color = RadarColors.Danger)
                    }
                    Icon(Icons.Filled.ChevronRight, "Editar regra")
                }
            }
        }

        // Status / pendências
        item {
            SectionCard("Status") {
                StatusRow(hasLocation, "Localização", if (here != null) "GPS ok" else if (hasLocation) "Aguardando GPS…" else "Permissão necessária") {
                    requestAndStart()
                }
                StatusRow(hasNotif, "Notificações", if (hasNotif) "Permitidas" else "Permissão necessária") {
                    ctx.startActivity(Intent(AndroidSettings.ACTION_APP_NOTIFICATION_SETTINGS)
                        .putExtra(AndroidSettings.EXTRA_APP_PACKAGE, ctx.packageName))
                }
                if (!running) {
                    StatusRow(false, "Radar parado", "O leitor e os alertas precisam do radar ligado — toque em Iniciar radar") {
                        requestAndStart()
                    }
                }
                if (settings.sourceUberScreen) {
                    // Ligado nas configurações, mas o Android não está executando (comum após atualizar o app).
                    val stuck = readerOn && readerConnectedAt == null &&
                        tick - br.com.radardinamico.service.Diagnostics.processStartedAt > 8_000
                    StatusRow(
                        readerOn && !stuck, "Leitor de tela do Uber",
                        when {
                            !readerOn -> "Desligado — toque para ativar em Acessibilidade. Se estiver bloqueado, " +
                                "vá em Informações do app → ⋮ → Permitir configurações restritas."
                            stuck -> "O Android parou o leitor. Toque, DESATIVE e ATIVE de novo o Radar Dinâmico em Acessibilidade."
                            else -> (readerStatus ?: "Ativo — abra o Uber Driver") +
                                (lastRead?.let { " • ${timeAgo(it, tick)}" } ?: "")
                        },
                    ) { ctx.startActivity(Intent(AndroidSettings.ACTION_ACCESSIBILITY_SETTINGS)) }
                }
                if (settings.sourceUberNotifications) {
                    StatusRow(
                        notifReaderOn, "Avisos do Uber (durante corridas)",
                        if (!notifReaderOn) "Toque para permitir acesso às notificações"
                        else uberNotif?.let { "Último: ${it.take(60)}" } ?: "Ativo — aguardando avisos de demanda",
                    ) { ctx.startActivity(Intent(AndroidSettings.ACTION_NOTIFICATION_LISTENER_SETTINGS)) }
                }
                if (settings.sourceCommunity) {
                    StatusRow(community?.startsWith("Falha") != true, "Comunidade", community ?: "Aguardando sincronização")
                }
            }
        }

        // Registro rápido
        item {
            SectionCard("Marcar dinâmico onde estou") {
                Text(
                    "Viu um dinâmico no app? Registre com um toque — isso alimenta o histórico e o ranking de regiões.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodyMedium,
                )
                Spacer(Modifier.height(8.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(4, 6, 8, 10, 15, 20).forEach { v ->
                        FilledTonalButton(onClick = {
                            val ok = RadarService.markHere(ctx, SurgeKind.MONEY, v.toDouble())
                            Toast.makeText(ctx, if (ok) "+R$ $v registrado" else "Sem GPS: inicie o radar", Toast.LENGTH_SHORT).show()
                        }, modifier = Modifier.height(48.dp)) { Text("+R$ $v", fontWeight = FontWeight.Bold) }
                    }
                    TextButton(onClick = { customDialog = true }, modifier = Modifier.height(48.dp)) { Text("Outro valor") }
                }
            }
        }

        item {
            Text(
                "Dinâmicos ativos (últimos ${settings.freshnessMin} min)",
                style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold,
            )
        }
        if (fresh.isEmpty()) {
            item {
                Text(
                    "Nenhuma leitura recente. Com o radar ligado, abra o mapa do Uber Driver ou marque manualmente.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        items(fresh, key = { it.first.id }) { (e, dist) ->
            val inRadius = dist == null || dist <= settings.radiusKm
            SectionCard(modifier = Modifier.clickable {
                ctx.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("geo:${e.lat},${e.lng}?q=${e.lat},${e.lng}(Dinâmico)")))
            }) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        e.label(), color = if (inRadius) RadarColors.Surge else MaterialTheme.colorScheme.onSurfaceVariant,
                        fontSize = 26.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(130.dp),
                    )
                    Column(Modifier.weight(1f)) {
                        val bairro by androidx.compose.runtime.produceState(
                            Neighborhood.cached(ctx, GeoPoint(e.lat, e.lng)), e.id, settings.showNeighborhood,
                        ) {
                            if (settings.showNeighborhood && value == null) value = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
                                Neighborhood.resolve(ctx, GeoPoint(e.lat, e.lng))
                            }
                        }
                        if (settings.showNeighborhood) bairro?.let {
                            Text(it.uppercase(), fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
                        }
                        Text(
                            when {
                                dist == null -> "Sem GPS"
                                dist < 0.15 -> "No seu local"
                                else -> "${Geo.formatKm(dist)} • ${Geo.cardinal(here!!, GeoPoint(e.lat, e.lng))}"
                            },
                            fontWeight = FontWeight.SemiBold,
                        )
                        Text("${e.source.label} • ${timeAgo(e.timestamp, tick)}",
                            color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                    }
                    Icon(Icons.Filled.ChevronRight, "Navegar")
                }
            }
        }
        item { Spacer(Modifier.height(24.dp)) }
    }

    if (customDialog) {
        var text by remember { mutableStateOf("") }
        var mult by remember { mutableStateOf(false) }
        AlertDialog(
            onDismissRequest = { customDialog = false },
            title = { Text("Registrar dinâmico") },
            text = {
                Column {
                    OutlinedTextField(
                        value = text, onValueChange = { text = it },
                        label = { Text(if (mult) "Multiplicador (ex.: 1,8)" else "Valor em R$ (ex.: 7,50)") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal), singleLine = true,
                    )
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        androidx.compose.material3.Switch(checked = mult, onCheckedChange = { mult = it })
                        Spacer(Modifier.width(8.dp)); Text("É multiplicador (x)")
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    val v = text.replace(',', '.').toDoubleOrNull()
                    if (v != null && v > 0) {
                        val ok = RadarService.markHere(ctx, if (mult) SurgeKind.MULTIPLIER else SurgeKind.MONEY, v)
                        if (!ok) Toast.makeText(ctx, "Sem GPS: inicie o radar", Toast.LENGTH_SHORT).show()
                        customDialog = false
                    }
                }) { Text("Salvar") }
            },
            dismissButton = { TextButton(onClick = { customDialog = false }) { Text("Cancelar") } },
        )
    }
}

@Composable
private fun StatusRow(ok: Boolean, title: String, detail: String, onClick: (() -> Unit)? = null) {
    Row(
        Modifier.fillMaxWidth()
            .then(if (onClick != null && !ok) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            if (ok) Icons.Filled.CheckCircle else Icons.Filled.ErrorOutline, null,
            tint = if (ok) RadarColors.Good else RadarColors.Surge,
        )
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontWeight = FontWeight.SemiBold)
            Text(detail, color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
        }
        if (onClick != null && !ok) Icon(Icons.Filled.ChevronRight, null)
    }
}
