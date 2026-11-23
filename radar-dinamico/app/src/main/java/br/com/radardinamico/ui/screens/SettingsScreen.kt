package br.com.radardinamico.ui.screens

import android.content.Intent
import android.provider.Settings as AndroidSettings
import android.widget.Toast
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Slider
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import br.com.radardinamico.data.Settings
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.ui.theme.RadarColors
import kotlin.math.roundToInt

private data class Preset(val name: String, val desc: String, val apply: (Settings) -> Settings)

private val presets = listOf(
    Preset("Seletivo", "+R$ 10, raio 3 km") { it.copy(minMoney = 10.0, minMultiplier = 1.8, radiusKm = 3.0, cooldownMin = 10) },
    Preset("Equilibrado", "+R$ 6, raio 5 km") { it.copy(minMoney = 6.0, minMultiplier = 1.5, radiusKm = 5.0, cooldownMin = 5) },
    Preset("Caçador", "+R$ 4, raio 10 km") { it.copy(minMoney = 4.0, minMultiplier = 1.3, radiusKm = 10.0, cooldownMin = 3) },
)

private val dayNames = listOf(1 to "Dom", 2 to "Seg", 3 to "Ter", 4 to "Qua", 5 to "Qui", 6 to "Sex", 7 to "Sáb")

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun SettingsScreen(modifier: Modifier) {
    val ctx = LocalContext.current
    val s by SettingsStore.state.collectAsState()
    fun set(t: (Settings) -> Settings) = SettingsStore.update(ctx, t)
    var confirmClear by remember { mutableStateOf(false) }

    LazyColumn(modifier.fillMaxSize().padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Spacer(Modifier.height(12.dp))
            Text("Ajustes", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text("Você decide como o radar trabalha.", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        item {
            SectionCard("Perfis rápidos") {
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    presets.forEach { p ->
                        OutlinedButton(onClick = { set(p.apply) }) {
                            Column { Text(p.name, fontWeight = FontWeight.SemiBold); Text(p.desc, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }
            }
        }

        item {
            SectionCard("Regras de alerta") {
                ToggleRow("Alertas ligados", "Receber notificações de dinâmico", s.alertsEnabled) { v -> set { it.copy(alertsEnabled = v) } }
                SliderRow("Dinâmico mínimo", "+R$ ${s.minMoney.roundToInt()}", s.minMoney.toFloat(), 1f..30f, 28) { v ->
                    set { it.copy(minMoney = v.roundToInt().toDouble()) }
                }
                SliderRow("Raio de busca", "${s.radiusKm.roundToInt()} km", s.radiusKm.toFloat(), 1f..30f, 28) { v ->
                    set { it.copy(radiusKm = v.roundToInt().toDouble()) }
                }
                ToggleRow("Alertar por multiplicador", "Para cidades que mostram 1,5x, 2,0x…", s.useMultiplier) { v -> set { it.copy(useMultiplier = v) } }
                if (s.useMultiplier) {
                    SliderRow("Multiplicador mínimo", "%.1fx".format(s.minMultiplier).replace('.', ','), s.minMultiplier.toFloat(), 1.1f..3f, 18) { v ->
                        set { it.copy(minMultiplier = (v * 10).roundToInt() / 10.0) }
                    }
                }
                SliderRow("Validade da leitura", "${s.freshnessMin} min", s.freshnessMin.toFloat(), 5f..60f, 10) { v ->
                    set { it.copy(freshnessMin = v.roundToInt()) }
                }
                SliderRow("Intervalo entre alertas da mesma região", "${s.cooldownMin} min", s.cooldownMin.toFloat(), 1f..30f, 28) { v ->
                    set { it.copy(cooldownMin = v.roundToInt()) }
                }
            }
        }

        item {
            SectionCard("Horário de funcionamento") {
                ToggleRow("Só alertar em horários definidos", "Fora do horário o radar fica em silêncio", s.scheduleEnabled) { v ->
                    set { it.copy(scheduleEnabled = v) }
                }
                if (s.scheduleEnabled) {
                    SliderRow("Início", "%02dh".format(s.startHour), s.startHour.toFloat(), 0f..23f, 22) { v -> set { it.copy(startHour = v.roundToInt()) } }
                    SliderRow("Fim", "%02dh".format(s.endHour), s.endHour.toFloat(), 0f..23f, 22) { v -> set { it.copy(endHour = v.roundToInt()) } }
                    if (s.startHour > s.endHour) Text("Faixa noturna: atravessa a meia-noite.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(8.dp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        dayNames.forEach { (d, n) ->
                            FilterChip(selected = d in s.activeDays, onClick = {
                                set { it.copy(activeDays = if (d in it.activeDays) it.activeDays - d else it.activeDays + d) }
                            }, label = { Text(n) })
                        }
                    }
                }
            }
        }

        item {
            SectionCard("Como avisar") {
                ToggleRow("Som", null, s.sound) { v -> set { it.copy(sound = v) } }
                ToggleRow("Vibração", null, s.vibrate) { v -> set { it.copy(vibrate = v) } }
                ToggleRow("Bairro em destaque", "Título da notificação com o bairro da zona, ex.: COSMOS — Dinâmico +R$ 15 • 300 m", s.showNeighborhood) { v ->
                    set { it.copy(showNeighborhood = v) }
                }
                ToggleRow("Falar em voz alta", "Ex.: \"Dinâmico de 8 reais, a 2 quilômetros\" — sem tirar os olhos da via", s.speak) { v ->
                    set { it.copy(speak = v) }
                }
                ToggleRow("Sugestões pelo histórico", "Avisa quando uma região costuma pagar bem neste horário", s.suggestionsEnabled) { v ->
                    set { it.copy(suggestionsEnabled = v) }
                }
                if (s.suggestionsEnabled) {
                    SliderRow("Frequência das sugestões", "a cada ${s.suggestionIntervalMin} min", s.suggestionIntervalMin.toFloat(), 10f..120f, 10) { v ->
                        set { it.copy(suggestionIntervalMin = v.roundToInt()) }
                    }
                }
            }
        }

        item {
            SectionCard("Fontes de dados") {
                ToggleRow("Ler a tela do Uber Driver (experimental)",
                    "Usa Acessibilidade para ler os valores de dinâmico exibidos. Somente leitura.", s.sourceUberScreen) { v ->
                    set { it.copy(sourceUberScreen = v) }
                }
                if (s.sourceUberScreen) {
                    TextButton(onClick = { ctx.startActivity(Intent(AndroidSettings.ACTION_ACCESSIBILITY_SETTINGS)) }) {
                        Text(if (isReaderEnabled(ctx)) "Leitor ativo — abrir Acessibilidade" else "Ativar leitor em Acessibilidade")
                    }
                }
                ToggleRow("Ler avisos do Uber Driver",
                    "Notificações de demanda alta do próprio Uber — funcionam durante a corrida, com o mapa fora da tela", s.sourceUberNotifications) { v ->
                    set { it.copy(sourceUberNotifications = v) }
                }
                if (s.sourceUberNotifications) {
                    TextButton(onClick = { ctx.startActivity(Intent(AndroidSettings.ACTION_NOTIFICATION_LISTENER_SETTINGS)) }) {
                        Text(if (isNotificationReaderEnabled(ctx)) "Acesso às notificações ativo" else "Permitir acesso às notificações")
                    }
                }
                ToggleRow("Receber da comunidade", "Dinâmicos compartilhados por outros motoristas (requer servidor)", s.sourceCommunity) { v ->
                    set { it.copy(sourceCommunity = v) }
                }
                ToggleRow("Compartilhar minhas leituras", "Envia valor + local ao servidor da comunidade", s.shareToCommunity) { v ->
                    set { it.copy(shareToCommunity = v) }
                }
                if (s.sourceCommunity || s.shareToCommunity) {
                    var url by remember(s.communityUrl) { mutableStateOf(s.communityUrl) }
                    OutlinedTextField(
                        value = url, onValueChange = { url = it },
                        label = { Text("URL do servidor") }, placeholder = { Text("https://meu-servidor.com/api") },
                        singleLine = true, modifier = Modifier.fillMaxWidth(),
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                    )
                    TextButton(onClick = { set { it.copy(communityUrl = url.trim()) } }) { Text("Salvar URL") }
                    SliderRow("Atualizar a cada", "${s.pollSeconds} s", s.pollSeconds.toFloat(), 15f..300f, 18) { v ->
                        set { it.copy(pollSeconds = v.roundToInt()) }
                    }
                }
            }
        }

        item {
            val debug by br.com.radardinamico.service.RadarState.readerDebug.collectAsState()
            SectionCard("Calibração do leitor (distância das zonas)") {
                Text(
                    "O app calcula onde fica cada zona pela posição do rótulo no mapa do Uber. " +
                        "Se as distâncias parecerem curtas ou longas demais, ajuste o zoom. Use o mapa do Uber " +
                        "com o norte para cima e centralizado no seu carro.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall,
                )
                SliderRow("Zoom do mapa do Uber", "%.1f".format(s.mapZoom).replace('.', ','), s.mapZoom.toFloat(), 11f..17f, 11) { v ->
                    set { it.copy(mapZoom = (v * 2).roundToInt() / 2.0) }
                }
                Text("Maior = mapa mais aproximado = zonas mais perto de você.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                SliderRow("Altura do seu carro na tela", "${s.carYPercent}% do topo", s.carYPercent.toFloat(), 20f..80f, 11) { v ->
                    set { it.copy(carYPercent = v.roundToInt()) }
                }
                Spacer(Modifier.height(8.dp))
                Text("Última leitura da tela", fontWeight = FontWeight.SemiBold)
                if (debug.isEmpty()) Text("Nada lido ainda.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                debug.forEach { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
            }
        }

        item {
            SectionCard("Dados") {
                Text("${SurgeRepository.get(ctx).count()} leituras salvas no aparelho.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                TextButton(onClick = { confirmClear = true }) { Text("Apagar histórico", color = RadarColors.Danger) }
            }
            Spacer(Modifier.height(24.dp))
        }
    }

    if (confirmClear) {
        AlertDialog(
            onDismissRequest = { confirmClear = false },
            title = { Text("Apagar histórico?") },
            text = { Text("O ranking de regiões será zerado. Esta ação não pode ser desfeita.") },
            confirmButton = {
                TextButton(onClick = {
                    SurgeRepository.get(ctx).clear(); confirmClear = false
                    Toast.makeText(ctx, "Histórico apagado", Toast.LENGTH_SHORT).show()
                }) { Text("Apagar", color = RadarColors.Danger) }
            },
            dismissButton = { TextButton(onClick = { confirmClear = false }) { Text("Cancelar") } },
        )
    }
}

@Composable
private fun ToggleRow(title: String, subtitle: String?, checked: Boolean, onChange: (Boolean) -> Unit) {
    Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f).padding(end = 12.dp)) {
            Text(title, fontWeight = FontWeight.Medium)
            subtitle?.let { Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall) }
        }
        Switch(checked = checked, onCheckedChange = onChange)
    }
}

@Composable
private fun SliderRow(
    title: String, valueLabel: String, value: Float, range: ClosedFloatingPointRange<Float>, steps: Int,
    onChange: (Float) -> Unit,
) {
    var local by remember(value) { mutableStateOf(value) }
    Column(Modifier.padding(vertical = 4.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(title, Modifier.weight(1f), fontWeight = FontWeight.Medium)
            Text(valueLabel, color = RadarColors.Surge, fontWeight = FontWeight.Bold)
        }
        Slider(value = local, onValueChange = { local = it; onChange(it) }, valueRange = range, steps = steps)
    }
}
