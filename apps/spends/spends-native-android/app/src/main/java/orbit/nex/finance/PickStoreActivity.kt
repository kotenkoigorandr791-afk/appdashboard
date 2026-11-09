package orbit.nex.finance

import android.content.Context
import android.app.Activity
import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import orbit.nex.finance.R
import orbit.nex.finance.data.Appearance
import orbit.nex.finance.data.Snapshot
import orbit.nex.finance.data.Store
import orbit.nex.finance.ui.AppBackground
import orbit.nex.finance.ui.RemotePhoto
import orbit.nex.finance.ui.SpendsTheme

class PickStoreActivity : AppCompatActivity() {
    override fun attachBaseContext(newBase: android.content.Context) {
        super.attachBaseContext(AppLocale.wrap(newBase))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val app = application as SpendsApplication
        setContent {
            val appearance by app.store.appearance.collectAsState(Appearance())
            val snapshot by app.store.snapshot.collectAsState(Snapshot())
            val stores = remember(snapshot) { storesByLastUse(snapshot) }
            SpendsTheme(appearance) {
                AppBackground {
                    PickStoreScreen(stores, { finish() }) { store ->
                        setResult(Activity.RESULT_OK, Intent().putExtra(EXTRA_STORE, store.name))
                        finish()
                    }
                }
            }
        }
    }
    companion object { const val EXTRA_STORE = "store" }
}

fun storesByLastUse(snapshot: Snapshot): List<Store> {
    val last = snapshot.expenses.groupBy { it.store }.mapValues { e -> e.value.maxOf { it.date } }
    return snapshot.stores.sortedByDescending { last[it.name] ?: "" }
}

@OptIn(ExperimentalMaterial3Api::class)
@androidx.compose.runtime.Composable
private fun PickStoreScreen(stores: List<Store>, onBack: () -> Unit, onPick: (Store) -> Unit) {
    Scaffold(
        containerColor = Color.Transparent,
        topBar = {
            TopAppBar(title = { Text(stringResource(R.string.choose_store), modifier = Modifier.fillMaxWidth().padding(end = 8.dp), textAlign = androidx.compose.ui.text.style.TextAlign.End) }, navigationIcon = {
                IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) }
            })
        },
    ) { padding ->
        LazyVerticalGrid(
            GridCells.Fixed(2),
            Modifier.fillMaxSize().padding(padding).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(stores, key = { it.id.ifBlank { it.name } }) { store ->
                Card(Modifier.fillMaxWidth().clickable { onPick(store) }, RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                    Column(Modifier.fillMaxWidth().padding(14.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        RemotePhoto(store.photoUrl, store.name.take(1).uppercase(), round = false, size = 92.dp)
                        Text(store.name, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }
}
