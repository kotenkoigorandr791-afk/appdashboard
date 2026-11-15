package orbit.nex.steps.screens
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.EnterTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.slideInVertically
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.CalendarViewWeek
import androidx.compose.material.icons.filled.DirectionsWalk
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.GridView
import androidx.compose.material.icons.filled.Hub
import androidx.compose.material.icons.filled.Insights
import androidx.compose.material.icons.filled.LocationCity
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Public
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Route
import androidx.compose.material.icons.filled.Today
import androidx.compose.material.icons.filled.VerifiedUser
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.ScrollableTabRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import orbit.nex.steps.HealthConnectManager
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.components.AppEmptyState
import orbit.nex.steps.components.AppHeroSurface
import orbit.nex.steps.components.AppSectionCard
import orbit.nex.steps.components.AppStatusPill
import orbit.nex.steps.location.AchievementCard
import orbit.nex.steps.location.EarnedAchievements
import orbit.nex.steps.location.ServerStatistics
import orbit.nex.steps.location.StatisticsRepository
import orbit.nex.steps.location.WeekdayMovement
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONArray
import org.json.JSONObject
import java.time.LocalDate
import java.time.YearMonth
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.text.NumberFormat
import java.util.Locale
import kotlin.math.roundToInt
data class StatMetric(val type: HealthDataType, val value: String)
data class StatsState(val byDay: Map<LocalDate, List<StatMetric>> = emptyMap(), val steps: Map<LocalDate, Long> = emptyMap(), val error: String? = null)
private enum class StatsTab(val titleRes: Int, val icon: ImageVector) {
    OVERVIEW(R.string.stats_tab_overview, Icons.Filled.Insights),
    ACTIVITY(R.string.stats_tab_activity, Icons.Filled.DirectionsWalk),
    MOVEMENT(R.string.stats_tab_movement, Icons.Filled.Route),
    ACHIEVEMENTS(R.string.stats_tab_achievements, Icons.Filled.EmojiEvents),
    QUALITY(R.string.stats_tab_quality, Icons.Filled.VerifiedUser),
}

@Composable
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
fun StatisticsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    val repository = remember { StatisticsRepository(prefs) }
    val reduceMotion = remember {
        android.provider.Settings.Global.getFloat(
            context.contentResolver,
            android.provider.Settings.Global.ANIMATOR_DURATION_SCALE,
            1f,
        ) == 0f
    }
    var selectedTab by rememberSaveable { mutableStateOf(StatsTab.OVERVIEW.name) }
    val tab = StatsTab.entries.firstOrNull { it.name == selectedTab } ?: StatsTab.OVERVIEW
    var monthOffset by remember { mutableIntStateOf(0) }
    var goalSteps by remember { mutableIntStateOf(0) }
    var state by remember { mutableStateOf(StatsState()) }
    var localMonthSteps by remember { mutableStateOf<Map<LocalDate, Long>>(emptyMap()) }
    var androidSteps by remember { mutableStateOf<Long?>(null) }
    var androidStepsError by remember { mutableStateOf<String?>(null) }
    var selectedStatsDate by remember { mutableStateOf<LocalDate?>(null) }
    var currentConfigUrl by remember { mutableStateOf(prefs.getWebhookConfigs().firstOrNull { it.isEnabled }?.url) }
    var serverStats by remember { mutableStateOf(ServerStatistics()) }
    var achievements by remember { mutableStateOf(EarnedAchievements()) }
    var seenAchievements by remember { mutableStateOf(prefs.getSeenAchievements()) }
    var serverLoading by remember { mutableStateOf(false) }
    var achievementsLoading by remember { mutableStateOf(false) }
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    val connected = config != null
    val selectedDate = LocalDate.now()
    val chartMonth = YearMonth.now().plusMonths(monthOffset.toLong())

    suspend fun load(force: Boolean = false) {
        state = config?.let { fetchYearStats(it, prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }, prefs, force, context) } ?: StatsState()
    }
    suspend fun loadLocalMonth() {
        if (connected) {
            localMonthSteps = emptyMap()
            return
        }
        val cached = cachedMonthSteps(chartMonth, prefs)
        val fresh = readAndroidMonthSteps(context, chartMonth)
        localMonthSteps = cached + fresh
    }
    suspend fun loadAndroidSteps(force: Boolean = false) {
        if (connected) {
            androidSteps = null
            androidStepsError = null
            return
        }
        androidStepsError = null
        if (!force) {
            prefs.getAndroidStepsCache(selectedDate.toString(), ANDROID_CACHE_MAX_AGE_MS)?.let {
                androidSteps = it
                return
            }
        }
        readAndroidSteps(context, selectedDate)
            .onSuccess { androidSteps = it }
            .onFailure {
                androidSteps = 0
                androidStepsError = it.message
            }
    }
    suspend fun loadServer(force: Boolean = false) {
        val active = config ?: return
        if (serverLoading || (!force && serverStats.hasData)) return
        serverLoading = true
        serverStats = repository.load(active)
        serverLoading = false
    }
    suspend fun loadAchievements(force: Boolean = false) {
        val active = config ?: return
        if (achievementsLoading || (!force && achievements.cards.isNotEmpty())) return
        achievementsLoading = true
        achievements = repository.achievements(active)
        achievementsLoading = false
    }

    LaunchedEffect(Unit) {
        while (true) {
            val newUrl = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }?.url
            if (newUrl != currentConfigUrl) {
                currentConfigUrl = newUrl
                state = StatsState()
                serverStats = ServerStatistics()
                achievements = EarnedAchievements()
                load()
            }
            delay(2_000)
        }
    }
    LaunchedEffect(currentConfigUrl) { load() }
    LaunchedEffect(currentConfigUrl) {
        goalSteps = config?.let { fetchStepGoal(it, prefs) } ?: 0
    }
    LaunchedEffect(chartMonth, currentConfigUrl) { loadLocalMonth() }
    LaunchedEffect(selectedDate, currentConfigUrl) {
        while (true) {
            loadAndroidSteps()
            delay(ANDROID_CACHE_MAX_AGE_MS)
        }
    }
    LaunchedEffect(tab, currentConfigUrl) {
        when (tab) {
            StatsTab.MOVEMENT, StatsTab.QUALITY -> loadServer()
            StatsTab.ACHIEVEMENTS -> {
                loadServer()
                loadAchievements()
            }
            else -> Unit
        }
    }

    val monthSteps = if (connected) state.steps else localMonthSteps
    val daySteps = if (connected) state.steps[selectedDate] ?: 0L else androidSteps ?: 0L
    val dayStepsError = if (connected) null else androidStepsError
    val number = NumberFormat.getIntegerInstance(Locale.getDefault())
    val yearSteps = remember(monthSteps) {
        monthSteps.filterKeys { it.year == selectedDate.year }.values.sum()
    }

    LazyColumn(
        modifier.fillMaxSize().padding(horizontal = 12.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 22.dp),
    ) {
        item {
            AppHeroSurface {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        stringResource(R.string.stats_private_eyebrow).uppercase(),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.primary,
                        fontWeight = FontWeight.Bold,
                    )
                    Text(
                        stringResource(R.string.stats_experience_title),
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                    )
                    Text(
                        stringResource(R.string.stats_experience_subtitle),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            number.format(daySteps),
                            style = MaterialTheme.typography.headlineLarge,
                            fontWeight = FontWeight.Bold,
                        )
                        Text(
                            stringResource(R.string.stats_steps_today_suffix),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Spacer(Modifier.weight(1f))
                        AppStatusPill(
                            if (serverStats.processing) stringResource(R.string.stats_processing) else stringResource(R.string.stats_private),
                            highlighted = serverStats.processing,
                        )
                    }
                    dayStepsError?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
                }
            }
        }

        item {
            ScrollableTabRow(
                selectedTabIndex = tab.ordinal,
                containerColor = Color.Transparent,
                contentColor = MaterialTheme.colorScheme.primary,
                edgePadding = 0.dp,
                divider = {},
            ) {
                StatsTab.entries.forEach { item ->
                    Tab(
                        selected = tab == item,
                        onClick = { selectedTab = item.name },
                        text = { Text(stringResource(item.titleRes), maxLines = 1, style = MaterialTheme.typography.labelMedium) },
                        icon = { Icon(item.icon, contentDescription = null, modifier = Modifier.size(17.dp)) },
                    )
                }
            }
        }

        when (tab) {
            StatsTab.OVERVIEW -> {
                item {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        OverviewMetric(
                            stringResource(R.string.stats_active_days),
                            number.format(serverStats.activeDays),
                            stringResource(R.string.stats_active_days_hint),
                            Modifier.weight(1f),
                        )
                        OverviewMetric(
                            stringResource(R.string.stats_distance),
                            formatDistance(serverStats.routeDistanceM),
                            stringResource(R.string.stats_distance_hint),
                            Modifier.weight(1f),
                        )
                    }
                }
                item {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        OverviewMetric(
                            stringResource(R.string.stats_places),
                            number.format(serverStats.places.size),
                            stringResource(R.string.stats_places_hint),
                            Modifier.weight(1f),
                        )
                        OverviewMetric(
                            stringResource(R.string.stats_earned),
                            number.format(achievements.earnedCount),
                            stringResource(R.string.stats_earned_hint),
                            Modifier.weight(1f),
                        )
                    }
                }
                item {
                    AppSectionCard(
                        title = stringResource(R.string.stats_steps_this_year),
                        kicker = stringResource(R.string.stats_this_year),
                        icon = Icons.AutoMirrored.Filled.TrendingUp,
                    ) {
                        Text(number.format(yearSteps), style = MaterialTheme.typography.displaySmall, fontWeight = FontWeight.Bold)
                        Text(
                            stringResource(R.string.stats_steps),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                item {
                    AppSectionCard(title = stringResource(R.string.stats_reach), icon = Icons.Filled.Public) {
                        val reach = serverStats.countries.take(6) + serverStats.cities.take(6)
                        if (reach.isEmpty()) {
                            Text(
                                stringResource(R.string.stats_reach_empty),
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        } else {
                            FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                reach.forEach { AppStatusPill(it) }
                            }
                        }
                    }
                }
            }

            StatsTab.ACTIVITY -> {
                item {
                    AppSectionCard(
                        title = chartMonth.format(DateTimeFormatter.ofPattern("MMMM yyyy", Locale.getDefault())),
                        icon = Icons.Filled.BarChart,
                        action = {
                            Row {
                                IconButton(onClick = { if (monthOffset > -11) monthOffset-- }) {
                                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = stringResource(R.string.stats_previous_month))
                                }
                                IconButton(onClick = { if (monthOffset < 0) monthOffset++ }) {
                                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = stringResource(R.string.stats_next_month))
                                }
                            }
                        },
                    ) {
                        StepsChart(monthSteps, chartMonth, goalSteps) { selectedStatsDate = it }
                    }
                }
                item {
                    AppSectionCard(title = stringResource(R.string.stats_heatmap_title), icon = Icons.Filled.GridView) {
                        StepsHeatmap(monthSteps, chartMonth, goalSteps) { selectedStatsDate = it }
                    }
                }
                if (!connected && localMonthSteps.isNotEmpty()) {
                    item { Text(stringResource(R.string.stats_local_data), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall) }
                }
                if (connected) {
                    val metrics = state.byDay[selectedDate].orEmpty()
                    if (metrics.isNotEmpty()) {
                        item {
                            AppSectionCard(title = stringResource(R.string.stats_today), icon = Icons.Filled.Today) {
                                metrics.forEach { metric ->
                                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text(stringResource(metric.type.nameResId))
                                        Text(metric.value, fontWeight = FontWeight.SemiBold)
                                    }
                                }
                            }
                        }
                    }
                }
                state.error?.let { error -> item { AppEmptyState(error, isError = true) } }
            }

            StatsTab.MOVEMENT -> {
                if (serverLoading && !serverStats.hasData) {
                    item { LoadingCard(stringResource(R.string.stats_loading)) }
                } else if (serverStats.error != null) {
                    item { AppEmptyState(stringResource(R.string.stats_unavailable), isError = true) }
                } else {
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OverviewMetric(stringResource(R.string.stats_route_distance), formatDistance(serverStats.routeDistanceM), stringResource(R.string.stats_confirmed_only), Modifier.weight(1f))
                            OverviewMetric(stringResource(R.string.stats_trips), number.format(serverStats.trips), stringResource(R.string.stats_avg_trip) + " " + formatDistance(serverStats.averageTripM), Modifier.weight(1f))
                        }
                    }
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OverviewMetric(stringResource(R.string.stats_active_time), formatDuration(serverStats.activeSeconds), stringResource(R.string.stats_active_time_hint), Modifier.weight(1f))
                            OverviewMetric(stringResource(R.string.stats_top_place), serverStats.places.firstOrNull()?.name ?: "—", stringResource(R.string.stats_visits, serverStats.places.firstOrNull()?.visits ?: 0), Modifier.weight(1f))
                        }
                    }
                    item {
                        AppSectionCard(title = stringResource(R.string.stats_week_rhythm), icon = Icons.Filled.CalendarViewWeek) {
                            WeeklyRhythm(serverStats.byWeekday)
                        }
                    }
                }
            }

            StatsTab.ACHIEVEMENTS -> {
                item {
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(
                                stringResource(R.string.stats_earned_only).uppercase(),
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.primary,
                                fontWeight = FontWeight.Bold,
                            )
                            Text(stringResource(R.string.stats_milestones), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
                        }
                        Text(
                            number.format(achievements.earnedCount),
                            style = MaterialTheme.typography.headlineMedium,
                            color = MaterialTheme.colorScheme.primary,
                            fontWeight = FontWeight.Bold,
                        )
                    }
                }
                when {
                    achievementsLoading && achievements.cards.isEmpty() -> item { LoadingCard(stringResource(R.string.stats_loading)) }
                    achievements.error != null -> item { AppEmptyState(stringResource(R.string.stats_achievements_unavailable), isError = true) }
                    !achievements.enabled -> item { AppEmptyState(stringResource(R.string.stats_achievements_disabled)) }
                    achievements.cards.isEmpty() -> item { AppEmptyState(stringResource(R.string.stats_no_achievements)) }
                    else -> item {
                        val rows = (achievements.cards.size + 1) / 2
                        LazyVerticalGrid(
                            columns = GridCells.Fixed(2),
                            modifier = Modifier.fillMaxWidth().height((rows * 174).dp),
                            userScrollEnabled = false,
                            horizontalArrangement = Arrangement.spacedBy(9.dp),
                            verticalArrangement = Arrangement.spacedBy(9.dp),
                        ) {
                            itemsIndexed(achievements.cards, key = { _, card -> card.id }) { index, card ->
                                EarnedAchievementCard(
                                    card = card,
                                    index = index,
                                    reduceMotion = reduceMotion,
                                    seen = card.id in seenAchievements,
                                    onSeen = {
                                        prefs.markAchievementsSeen(setOf(card.id))
                                        seenAchievements = seenAchievements + card.id
                                    },
                                )
                            }
                        }
                    }
                }
            }

            StatsTab.QUALITY -> {
                if (serverLoading && !serverStats.hasData) {
                    item { LoadingCard(stringResource(R.string.stats_loading)) }
                } else if (serverStats.error != null) {
                    item { AppEmptyState(stringResource(R.string.stats_unavailable), isError = true) }
                } else {
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OverviewMetric(stringResource(R.string.stats_coverage), "${(serverStats.coverageRatio * 100).roundToInt()}%", stringResource(R.string.stats_coverage_hint), Modifier.weight(1f))
                            OverviewMetric(stringResource(R.string.stats_accuracy), serverStats.accuracyM?.let { "±${it.roundToInt()} m" } ?: "—", stringResource(R.string.stats_accuracy_hint), Modifier.weight(1f))
                        }
                    }
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OverviewMetric(stringResource(R.string.stats_latest_point), StatisticsRepository.dateLabel(serverStats.latestPointAt).ifBlank { "—" }, stringResource(R.string.stats_latest_point_hint), Modifier.weight(1f))
                            OverviewMetric(stringResource(R.string.stats_devices), number.format(serverStats.devices.size), stringResource(R.string.stats_devices_hint), Modifier.weight(1f))
                        }
                    }
                    item {
                        AppSectionCard(title = stringResource(R.string.stats_sources), icon = Icons.Filled.Hub) {
                            val sources = serverStats.sources.entries.map { "${it.key} · ${number.format(it.value)}" } +
                                serverStats.devices.map { "${it.name} · ${it.status}" }
                            if (sources.isEmpty()) {
                                Text(stringResource(R.string.stats_no_sources), color = MaterialTheme.colorScheme.onSurfaceVariant)
                            } else {
                                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    sources.forEach { AppStatusPill(it) }
                                }
                            }
                            Text(
                                stringResource(R.string.stats_quality_note),
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }
        }

        item {
            OutlinedButton(
                onClick = {
                    scope.launch {
                        load(true)
                        loadAndroidSteps(true)
                        loadLocalMonth()
                        loadServer(true)
                        loadAchievements(true)
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Icon(Icons.Filled.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.size(8.dp))
                Text(stringResource(R.string.dashboard_refresh))
            }
        }
    }

    selectedStatsDate?.let { date ->
        ModalBottomSheet(onDismissRequest = { selectedStatsDate = null }) {
            Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(formatTooltipDate(date), style = MaterialTheme.typography.titleLarge)
                Text("${number.format(monthSteps[date] ?: 0L)} ${stringResource(R.string.stats_steps)}", style = MaterialTheme.typography.headlineSmall)
                state.byDay[date].orEmpty().forEach { metric ->
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(stringResource(metric.type.nameResId))
                        Text(metric.value)
                    }
                }
            }
        }
    }
}

@Composable
private fun OverviewMetric(label: String, value: String, hint: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        shape = MaterialTheme.shapes.medium,
        color = MaterialTheme.colorScheme.surfaceVariant,
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(
                label.uppercase(),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                fontWeight = FontWeight.Bold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(value, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(hint, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 2)
        }
    }
}

@Composable
private fun LoadingCard(text: String) {
    Surface(shape = MaterialTheme.shapes.medium, color = MaterialTheme.colorScheme.surfaceVariant, modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(22.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
            Text(text, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
private fun WeeklyRhythm(rows: List<WeekdayMovement>) {
    val labels = listOf(
        R.string.weekday_mon, R.string.weekday_tue, R.string.weekday_wed, R.string.weekday_thu,
        R.string.weekday_fri, R.string.weekday_sat, R.string.weekday_sun,
    )
    val max = rows.maxOfOrNull { it.trips }?.coerceAtLeast(1) ?: 1
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.Bottom) {
        labels.forEachIndexed { index, labelRes ->
            val trips = rows.getOrNull(index)?.trips ?: 0
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(trips.toString(), style = MaterialTheme.typography.labelSmall)
                Box(
                    Modifier
                        .fillMaxWidth()
                        .height((8 + 54 * (trips.toFloat() / max)).dp)
                        .background(
                            Brush.verticalGradient(listOf(MaterialTheme.colorScheme.primary, MaterialTheme.colorScheme.primary.copy(alpha = 0.35f))),
                            MaterialTheme.shapes.small,
                        )
                )
                Text(stringResource(labelRes), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun EarnedAchievementCard(
    card: AchievementCard,
    index: Int,
    reduceMotion: Boolean,
    seen: Boolean,
    onSeen: () -> Unit,
) {
    var visible by remember(card.id) { mutableStateOf(false) }
    LaunchedEffect(card.id, seen) {
        if (reduceMotion || seen) {
            visible = true
        } else {
            delay((index * 45L).coerceAtMost(315L))
            visible = true
            onSeen()
        }
    }
    AnimatedVisibility(
        visible = visible,
        enter = if (reduceMotion || seen) EnterTransition.None else fadeIn(tween(320)) + slideInVertically(tween(320)) { it / 4 },
    ) {
        Surface(
            shape = MaterialTheme.shapes.medium,
            color = MaterialTheme.colorScheme.primary.copy(alpha = 0.09f),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Column(Modifier.padding(13.dp), verticalArrangement = Arrangement.spacedBy(7.dp)) {
                Surface(
                    shape = MaterialTheme.shapes.small,
                    color = MaterialTheme.colorScheme.primary.copy(alpha = 0.14f),
                    modifier = Modifier.size(36.dp),
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(achievementIcon(card.icon), contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(20.dp))
                    }
                }
                Text(
                    stringResource(achievementTitleRes(card.titleKey)),
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    stringResource(achievementDescriptionRes(card.descriptionKey)),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 3,
                    overflow = TextOverflow.Ellipsis,
                )
                Spacer(Modifier.weight(1f))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text(achievementTier(card), style = MaterialTheme.typography.labelSmall)
                    Text(
                        StatisticsRepository.dateLabel(card.earnedAt),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }
    }
}

private fun achievementIcon(name: String): ImageVector = when (name) {
    "public" -> Icons.Filled.Public
    "map" -> Icons.Filled.Map
    "location_city" -> Icons.Filled.LocationCity
    "place" -> Icons.Filled.Place
    "route" -> Icons.Filled.Route
    else -> Icons.Filled.CalendarMonth
}

private fun achievementTitleRes(key: String): Int = when (key) {
    "achievement_family_countries" -> R.string.achievement_family_countries
    "achievement_family_regions" -> R.string.achievement_family_regions
    "achievement_family_cities" -> R.string.achievement_family_cities
    "achievement_family_places" -> R.string.achievement_family_places
    "achievement_family_distance" -> R.string.achievement_family_distance
    else -> R.string.achievement_family_coverage
}

private fun achievementDescriptionRes(key: String): Int = when (key) {
    "achievement_desc_countries" -> R.string.achievement_desc_countries
    "achievement_desc_regions" -> R.string.achievement_desc_regions
    "achievement_desc_cities" -> R.string.achievement_desc_cities
    "achievement_desc_places" -> R.string.achievement_desc_places
    "achievement_desc_distance" -> R.string.achievement_desc_distance
    else -> R.string.achievement_desc_coverage
}

private fun achievementTier(card: AchievementCard): String = when (card.unit) {
    "m" -> "${(card.target / 1000).roundToInt()} km"
    "days" -> "${card.target.roundToInt()}"
    else -> NumberFormat.getIntegerInstance(Locale.getDefault()).format(card.target)
}

private fun formatDistance(meters: Double): String =
    if (meters <= 0.0) "0 km"
    else if (meters < 1000) "${meters.roundToInt()} m"
    else String.format(Locale.getDefault(), "%.1f km", meters / 1000.0)

private fun formatDuration(seconds: Long): String {
    val hours = seconds / 3600
    val minutes = (seconds % 3600) / 60
    return if (hours > 0) "${hours}h ${minutes}m" else "${minutes}m"
}

@Composable
private fun StepsChart(steps: Map<LocalDate, Long>, month: YearMonth, goal: Int = 0, onDayClick: (LocalDate) -> Unit) {
    val barTop = MaterialTheme.colorScheme.primary
    val barBottom = MaterialTheme.colorScheme.tertiary
    val axisColor = MaterialTheme.colorScheme.onSurfaceVariant
    val gridColor = MaterialTheme.colorScheme.outlineVariant
    val goalColor = Color(0xFFE53935)
    val goalLabelBg = MaterialTheme.colorScheme.surface
    val goalLabel = stringResource(R.string.stats_goal, NumberFormat.getIntegerInstance(Locale.getDefault()).format(goal))
    val days = remember(steps, month) {
        (1..month.lengthOfMonth()).map { day -> month.atDay(day) to (steps[month.atDay(day)] ?: 0L) }
    }
    val density = LocalDensity.current
    val labelTextSize = with(density) { 10.sp.toPx() }
    val leftPad = with(density) { 38.dp.toPx() }
    val rightPad = with(density) { 6.dp.toPx() }
    val bottomPad = with(density) { 20.dp.toPx() }
    val topPad = with(density) { 8.dp.toPx() }
    var tooltip by remember { mutableStateOf<BarTooltip?>(null) }
    var tapX by remember { mutableFloatStateOf(0f) }
    var canvasWidth by remember { mutableFloatStateOf(0f) }
    fun showTooltip(offset: Offset) {
        val chartWidth = canvasWidth - leftPad - rightPad
        if (chartWidth <= 0f) return
        val barWidth = chartWidth / days.size.toFloat()
        val index = ((offset.x - leftPad) / barWidth).toInt().coerceIn(0, days.lastIndex)
        val (date, count) = days[index]
        if (count > 0L) {
            tapX = offset.x
            tooltip = BarTooltip(date, count)
            onDayClick(date)
        }
    }
    LaunchedEffect(tooltip) {
        if (tooltip != null) {
            delay(TOOLTIP_TIMEOUT_MS)
            tooltip = null
        }
    }
    Box(
        Modifier
            .fillMaxWidth()
            .onSizeChanged { canvasWidth = it.width.toFloat() }
    ) {
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(170.dp)
                .pointerInput(days) {
                    detectTapGestures(
                        onTap = { showTooltip(it) },
                        onLongPress = { showTooltip(it) }
                    )
                }
        ) {
            // Keep the goal visible with some headroom, like the web chart does.
            val dataMax = days.maxOfOrNull { it.second } ?: 0L
            val axisMax = if (goal > 0) maxOf(dataMax, (goal * 1.2).toLong()) else dataMax
            val max = axisMax.coerceAtLeast(1L).toFloat()
            val chartWidth = (size.width - leftPad - rightPad).coerceAtLeast(1f)
            val chartHeight = (size.height - topPad - bottomPad).coerceAtLeast(1f)
            val baseY = topPad + chartHeight
            val barWidth = chartWidth / days.size.toFloat()
            val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
                textSize = labelTextSize
                setColor(axisColor.toArgb())
            }
            listOf(0f, 0.5f, 1f).forEach { frac ->
                val y = baseY - chartHeight * frac
                drawLine(gridColor, Offset(leftPad, y), Offset(size.width - rightPad, y), strokeWidth = 1f)
                paint.textAlign = android.graphics.Paint.Align.RIGHT
                drawContext.canvas.nativeCanvas.drawText(
                    (max * frac).roundToInt().toString(),
                    leftPad - 6f,
                    y + labelTextSize / 3f,
                    paint
                )
            }
            val barRadius = CornerRadius((barWidth * 0.28f).coerceIn(1.5f, 5.dp.toPx()))
            days.forEachIndexed { index, (_, count) ->
                val gap = (barWidth * 0.10f).coerceAtLeast(2f)
                val h = if (max > 0f) (count / max) * chartHeight else 0f
                val x = leftPad + index * barWidth + gap / 2f
                val barWidthPx = (barWidth - gap).coerceAtLeast(1f)
                if (count > 0L) {
                    // Colourful like the Spends charts: a vertical gradient that
                    // shifts from the accent to the secondary accent over the bar.
                    drawRoundRect(
                        brush = Brush.verticalGradient(
                            listOf(barTop, barBottom.copy(alpha = 0.75f)),
                            startY = baseY - h,
                            endY = baseY,
                        ),
                        topLeft = Offset(x, baseY - h),
                        size = Size(barWidthPx, h.coerceAtLeast(2f)),
                        cornerRadius = barRadius,
                    )
                } else {
                    drawRoundRect(
                        color = gridColor.copy(alpha = 0.45f),
                        topLeft = Offset(x, baseY - 2f),
                        size = Size(barWidthPx, 2f),
                        cornerRadius = barRadius,
                    )
                }
            }
            if (goal > 0) {
                val goalY = baseY - chartHeight * (goal.toFloat() / max)
                drawLine(
                    color = goalColor,
                    start = Offset(leftPad, goalY),
                    end = Offset(size.width - rightPad, goalY),
                    strokeWidth = 2f,
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(12f, 8f))
                )
                // Label sits on a rounded plate so it stays legible over bars.
                paint.textAlign = android.graphics.Paint.Align.RIGHT
                paint.color = goalColor.toArgb()
                val textWidth = paint.measureText(goalLabel)
                val padH = 4.dp.toPx()
                val padV = 2.dp.toPx()
                val baseline = (goalY - 6f).coerceAtLeast(labelTextSize + padV)
                val right = size.width - rightPad
                drawRoundRect(
                    color = goalLabelBg,
                    topLeft = Offset(right - textWidth - padH * 2f, baseline - labelTextSize - padV),
                    size = Size(textWidth + padH * 2f, labelTextSize + padV * 2f),
                    cornerRadius = CornerRadius(4.dp.toPx(), 4.dp.toPx())
                )
                drawContext.canvas.nativeCanvas.drawText(
                    goalLabel,
                    right - padH,
                    baseline,
                    paint
                )
                paint.color = axisColor.toArgb()
            }
            paint.textAlign = android.graphics.Paint.Align.CENTER
            val xLabels = (sequenceOf(1) + (5..month.lengthOfMonth() step 5)).distinct().toList()
            xLabels.forEach { day ->
                val x = leftPad + (day - 1) * barWidth + barWidth / 2f
                drawContext.canvas.nativeCanvas.drawText(
                    day.toString(),
                    x,
                    size.height - bottomPad + labelTextSize + 4f,
                    paint
                )
            }
        }
        tooltip?.let { tip ->
            val halfWidth = with(density) { 66.dp.toPx() }
            val clampedX = (tapX - halfWidth).coerceIn(0f, (canvasWidth - halfWidth * 2f).coerceAtLeast(0f))
            Surface(
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .offset { IntOffset(clampedX.roundToInt(), 0) },
                shape = RoundedCornerShape(6.dp),
                color = MaterialTheme.colorScheme.inverseSurface,
                shadowElevation = 4.dp
            ) {
                Text(
                    text = "${formatTooltipDate(tip.date)}: ${NumberFormat.getIntegerInstance(Locale.getDefault()).format(tip.count)} ${stringResource(R.string.stats_tooltip_steps)}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.inverseOnSurface,
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                )
            }
        }
    }
}
@Composable
private fun StepsHeatmap(steps: Map<LocalDate, Long>, month: YearMonth, goal: Int, onDayClick: (LocalDate) -> Unit) {
    // Gold marks the days the daily step goal was reached.
    val goalGold = Color(0xFFD4A017)
    val days = remember(steps, month) { (1..month.lengthOfMonth()).map { month.atDay(it) } }
    val max = remember(days, steps) { (days.maxOfOrNull { steps[it] ?: 0L } ?: 0L).coerceAtLeast(1L) }
    val base = MaterialTheme.colorScheme.primary
    val warm = MaterialTheme.colorScheme.tertiary
    val empty = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.25f)
    var picked by remember { mutableStateOf<LocalDate?>(null) }
    // Weeks start on Monday; leading blanks keep the weekday columns aligned.
    val leading = month.atDay(1).dayOfWeek.value - 1
    val cells: List<LocalDate?> = List(leading) { null } + days
    val rows = cells.chunked(7)
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        rows.forEach { week ->
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp), modifier = Modifier.fillMaxWidth()) {
                week.forEach { date ->
                    if (date == null) {
                        Spacer(Modifier.weight(1f).aspectRatio(1f))
                    } else {
                        val count = steps[date] ?: 0L
                        val intensity = (count.toFloat() / max.toFloat()).coerceIn(0f, 1f)
                        val color = if (count <= 0L) empty else lerp(base, warm, intensity).copy(alpha = 0.35f + 0.65f * intensity)
                        val goalReached = goal > 0 && count >= goal
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .aspectRatio(1f)
                                .clip(RoundedCornerShape(4.dp))
                                .background(color)
                                .then(
                                    if (goalReached) {
                                        Modifier.border(1.5.dp, goalGold, RoundedCornerShape(4.dp))
                                    } else Modifier
                                )
                                .clickable { picked = if (picked == date) null else date; onDayClick(date) },
                            contentAlignment = Alignment.Center,
                        ) {
                            // Day of month so the grid reads as a calendar.
                            Text(
                                date.dayOfMonth.toString(),
                                style = MaterialTheme.typography.labelSmall,
                                color = when {
                                    goalReached -> goalGold
                                    intensity > 0.55f -> MaterialTheme.colorScheme.onPrimary
                                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                                },
                            )
                        }
                    }
                }
                // Pad the final short week so its cells keep the weekday width.
                repeat(7 - week.size) {
                    Spacer(Modifier.weight(1f).aspectRatio(1f))
                }
            }
        }
        val numberFormat = NumberFormat.getIntegerInstance(Locale.getDefault())
        val caption = picked?.let { date ->
            "${formatTooltipDate(date)}: ${numberFormat.format(steps[date] ?: 0L)} ${stringResource(R.string.stats_tooltip_steps)}"
        } ?: "${stringResource(R.string.stats_month_total)}: ${numberFormat.format(days.sumOf { steps[it] ?: 0L })}"
        Text(
            caption,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

private data class BarTooltip(val date: LocalDate, val count: Long)
private fun formatTooltipDate(date: LocalDate): String =
    DateTimeFormatter.ofPattern("d MMMM", Locale.getDefault()).format(date)
private const val TOOLTIP_TIMEOUT_MS = 2_000L
private suspend fun readAndroidSteps(context: android.content.Context, date: LocalDate): Result<Long> = withContext(Dispatchers.IO) {
    runCatching {
        val zone = ZoneId.systemDefault()
        val start = date.atStartOfDay(zone).toInstant()
        val end = date.plusDays(1).atStartOfDay(zone).toInstant()
        HealthConnectManager(context)
            .readHealthData(setOf(HealthDataType.STEPS), emptyMap(), start = start, end = end)
            .getOrThrow()
            .steps
            .sumOf { it.count }
            .also { PreferencesManager(context).setAndroidStepsCache(date.toString(), it) }
    }
}
private fun cachedMonthSteps(month: YearMonth, prefs: PreferencesManager): Map<LocalDate, Long> {
    val result = mutableMapOf<LocalDate, Long>()
    for (day in 1..month.lengthOfMonth()) {
        val date = month.atDay(day)
        prefs.getAndroidStepsCache(date.toString(), Long.MAX_VALUE)?.let { result[date] = it }
    }
    return result
}
private suspend fun readAndroidMonthSteps(context: android.content.Context, month: YearMonth): Map<LocalDate, Long> = withContext(Dispatchers.IO) {
    val prefs = PreferencesManager(context)
    runCatching {
        val manager = HealthConnectManager(context)
        if (HealthConnectManager.grantedDataTypes(manager.getGrantedPermissions()).none { it == HealthDataType.STEPS }) {
            return@runCatching cachedMonthSteps(month, prefs)
        }
        val zone = ZoneId.systemDefault()
        manager.readHealthData(
            enabledTypes = setOf(HealthDataType.STEPS),
            lastSyncTimestamps = emptyMap(),
            start = month.atDay(1).atStartOfDay(zone).toInstant(),
            end = month.plusMonths(1).atDay(1).atStartOfDay(zone).toInstant()
        )
            .getOrThrow()
            .steps
            .groupBy { it.startTime.atZone(zone).toLocalDate() }
            .mapValues { (_, records) -> records.sumOf { it.count } }
            .also { map -> map.forEach { (date, count) -> prefs.setAndroidStepsCache(date.toString(), count) } }
    }.getOrElse { cachedMonthSteps(month, prefs) }
}
private suspend fun fetchYearStats(config: WebhookConfig, enabled: Set<HealthDataType>, prefs: PreferencesManager, force: Boolean = false, context: android.content.Context): StatsState = withContext(Dispatchers.IO) {
    if (!force) {
        prefs.getStatsCache(STATS_CACHE_MAX_AGE_MS)?.let {
            return@withContext parseStatsCache(it, enabled, context) ?: StatsState()
        }
    }
    runCatching {
        val to = LocalDate.now()
        val from = to.minusDays(365)
        val baseUrl = config.url.trimEnd('/')
        // metrics=1 gives per-day metric details (limited to days with metric files),
        // the plain endpoint returns the full steps history from the server DB.
        val metricsUrl = "$baseUrl${if (baseUrl.contains("?")) "&" else "?"}from=$from&to=$to&metrics=1"
        val stepsUrl = "$baseUrl${if (baseUrl.contains("?")) "&" else "?"}from=$from&to=$to"
        val client = OkHttpClient()
        fun httpGet(url: String): String {
            val builder = Request.Builder().url(url)
            config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
            // The API binds a pairing token to one device and answers 400 without it.
            if (config.headers.keys.none { it.equals("X-Device-Id", ignoreCase = true) }) {
                builder.addHeader("X-Device-Id", prefs.getDeviceId())
            }
            return client.newCall(builder.build()).execute().use { response ->
                if (!response.isSuccessful) error("HTTP ${response.code}")
                response.body?.string().orEmpty()
            }
        }
        val metricsBody = httpGet(metricsUrl)
        val stepsBody = httpGet(stepsUrl)
        val combined = JSONObject()
            .put("metrics", JSONObject(metricsBody).optJSONArray("metrics"))
            .put("steps", JSONObject(stepsBody).optJSONArray("steps"))
        prefs.setStatsCache(combined.toString())
        parseServerMetrics(combined.optJSONArray("metrics") ?: JSONArray(), enabled, combined.optJSONArray("steps") ?: JSONArray(), context)
    }.getOrElse { failure ->
        parseStatsCache(prefs.getStatsCache(Long.MAX_VALUE), enabled, context)?.copy(error = failure.message)
            ?: StatsState(error = failure.message)
    }
}
private fun parseStatsCache(json: String?, enabled: Set<HealthDataType>, context: android.content.Context): StatsState? {
    val cached = json ?: return null
    return runCatching {
        parseServerMetrics(
            JSONObject(cached).optJSONArray("metrics") ?: JSONArray(),
            enabled,
            JSONObject(cached).optJSONArray("steps") ?: JSONArray(),
            context
        )
    }.getOrNull()
}
private fun parseServerMetrics(items: JSONArray, enabled: Set<HealthDataType>, serverSteps: JSONArray = JSONArray(), context: android.content.Context): StatsState {
    val byDay = mutableMapOf<LocalDate, List<StatMetric>>()
    val steps = mutableMapOf<LocalDate, Long>()
    for (index in 0 until serverSteps.length()) {
        val row = serverSteps.optJSONObject(index) ?: continue
        val date = runCatching { LocalDate.parse(row.optString("date")) }.getOrNull() ?: continue
        val s = row.optLong("steps", 0L)
        if (HealthDataType.STEPS in enabled && s > 0) steps[date] = s
    }
    for (index in 0 until items.length()) {
        val row = items.optJSONObject(index) ?: continue
        val date = runCatching { LocalDate.parse(row.optString("date")) }.getOrNull() ?: continue
        val day = row.optJSONObject("metrics") ?: continue
        val rows = mutableListOf<StatMetric>()
        fun metric(name: String): JSONObject? = day.optJSONObject(name)
        fun total(name: String): Double {
            val obj = metric(name) ?: return 0.0
            val total = obj.optDouble("total", Double.NaN)
            if (!total.isNaN()) return total
            val meters = obj.optDouble("total_meters", Double.NaN)
            return if (meters.isNaN()) 0.0 else meters
        }
        val stepTotal = total("steps").toLong()
        if (HealthDataType.STEPS in enabled && stepTotal > 0 && date !in steps) {
            steps[date] = stepTotal
        }
        // Units come from resources so every locale can localise them.
        fun unit(resId: Int, value: String): String = context.getString(resId, value)
        val candidates = listOf(
            HealthDataType.DISTANCE to ("distance" to unit(R.string.unit_meters, "%.0f".format(total("distance")))),
            HealthDataType.ACTIVE_CALORIES to ("active_calories" to unit(R.string.unit_kcal, "%.0f".format(total("active_calories")))),
            HealthDataType.TOTAL_CALORIES to ("total_calories" to unit(R.string.unit_kcal, "%.0f".format(total("total_calories")))),
            HealthDataType.SLEEP to ("sleep" to unit(R.string.unit_hours, "%.1f".format(total("sleep") / 3600.0))),
            HealthDataType.HEART_RATE to ("heart_rate" to unit(R.string.unit_bpm, "%.0f".format(metric("heart_rate")?.optDouble("avg") ?: 0.0))),
            HealthDataType.WEIGHT to ("weight" to unit(R.string.unit_kg, "%.1f".format(metric("weight")?.optDouble("last") ?: 0.0))),
            HealthDataType.BLOOD_PRESSURE to ("blood_pressure" to unit(R.string.unit_mmhg, "%.0f".format(metric("blood_pressure")?.optDouble("last") ?: 0.0))),
            HealthDataType.OXYGEN_SATURATION to ("oxygen_spo2" to unit(R.string.unit_percent, "%.1f".format(metric("oxygen_spo2")?.optDouble("avg") ?: 0.0)))
        )
        candidates.forEach { (type, pair) ->
            val obj = metric(pair.first)
            if (type in enabled && obj != null && obj.optInt("samples", 0) > 0) rows += StatMetric(type, pair.second)
        }
        byDay[date] = rows
    }
    return StatsState(byDay = byDay, steps = steps)
}
private const val STATS_CACHE_MAX_AGE_MS = 60L * 60 * 1000
private const val ANDROID_CACHE_MAX_AGE_MS = 30L * 1000

/** Daily step goal from the server settings, or 0 when it is unavailable. */
private suspend fun fetchStepGoal(config: WebhookConfig, prefs: PreferencesManager): Int = withContext(Dispatchers.IO) {
    runCatching {
        val url = config.url.trimEnd('/') + "/settings?settings=1"
        OkHttpClient().newCall(stepsRequest(config, prefs, url).build()).execute().use { response ->
            if (!response.isSuccessful) return@use 0
            val settings = JSONObject(response.body?.string().orEmpty()).optJSONObject("settings") ?: JSONObject()
            settings.optString("goal_steps").toIntOrNull() ?: 0
        }
    }.getOrDefault(0)
}
