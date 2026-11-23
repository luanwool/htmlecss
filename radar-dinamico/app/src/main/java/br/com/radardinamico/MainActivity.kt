package br.com.radardinamico

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Insights
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.Radar
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import br.com.radardinamico.ui.screens.InsightsScreen
import br.com.radardinamico.ui.screens.MapScreen
import br.com.radardinamico.ui.screens.RadarScreen
import br.com.radardinamico.ui.screens.SettingsScreen
import br.com.radardinamico.ui.theme.RadarTheme

private data class Tab(val label: String, val icon: ImageVector)

private val tabs = listOf(
    Tab("Radar", Icons.Filled.Radar),
    Tab("Mapa", Icons.Filled.Map),
    Tab("Melhores", Icons.Filled.Insights),
    Tab("Ajustes", Icons.Filled.Tune),
)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            RadarTheme {
                var tab by rememberSaveable { mutableIntStateOf(0) }
                Scaffold(
                    bottomBar = {
                        NavigationBar {
                            tabs.forEachIndexed { i, t ->
                                NavigationBarItem(
                                    selected = tab == i,
                                    onClick = { tab = i },
                                    icon = { Icon(t.icon, contentDescription = null) },
                                    label = { Text(t.label) },
                                )
                            }
                        }
                    },
                ) { pad ->
                    val m = Modifier.padding(pad)
                    when (tab) {
                        0 -> RadarScreen(m, onOpenSettings = { tab = 3 })
                        1 -> MapScreen(m)
                        2 -> InsightsScreen(m)
                        else -> SettingsScreen(m)
                    }
                }
            }
        }
    }
}
