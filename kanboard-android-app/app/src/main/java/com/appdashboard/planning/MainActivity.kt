package com.appdashboard.planning

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.appdashboard.planning.ui.navigation.Routes
import com.appdashboard.planning.ui.screens.CreateTaskScreen
import com.appdashboard.planning.ui.screens.MainShell
import com.appdashboard.planning.ui.screens.PairingScreen
import com.appdashboard.planning.ui.screens.TaskDetailScreen
import com.appdashboard.planning.ui.theme.PlanningTheme
import com.appdashboard.planning.ui.theme.rememberThemeController

class MainActivity : ComponentActivity() {
    private var pendingLink by mutableStateOf<String?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        pendingLink = intent?.data?.toString()
        val app = application as PlanningApp
        setContent {
            val themeController = rememberThemeController(app.session.themeMode())
            PlanningTheme(themeController.mode) {
                Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    AppRoot(
                        app = app,
                        themeController = themeController,
                        pendingLink = pendingLink,
                        onPendingConsumed = { pendingLink = null },
                    )
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        pendingLink = intent.data?.toString()
    }
}

@Composable
private fun AppRoot(
    app: PlanningApp,
    themeController: com.appdashboard.planning.ui.theme.ThemeController,
    pendingLink: String?,
    onPendingConsumed: () -> Unit,
) {
    val nav = rememberNavController()
    var paired by remember { mutableStateOf(app.session.isPaired()) }
    val start = if (paired) Routes.Main else Routes.Pairing

    NavHost(navController = nav, startDestination = start) {
        composable(Routes.Pairing) {
            PairingScreen(
                repository = app.repository,
                initialLink = pendingLink,
                onPaired = {
                    paired = true
                    onPendingConsumed()
                    nav.navigate(Routes.Main) {
                        popUpTo(Routes.Pairing) { inclusive = true }
                    }
                },
            )
        }
        composable(Routes.Main) {
            MainShell(
                app = app,
                themeController = themeController,
                onTask = { id -> nav.navigate(Routes.task(id)) },
                onCreateTask = { projectId -> nav.navigate(Routes.taskCreate(projectId)) },
                onDisconnected = {
                    paired = false
                    nav.navigate(Routes.Pairing) {
                        popUpTo(0) { inclusive = true }
                    }
                },
            )
        }
        composable(
            Routes.Task,
            arguments = listOf(navArgument("taskId") { type = NavType.IntType }),
        ) { entry ->
            val id = entry.arguments?.getInt("taskId") ?: return@composable
            TaskDetailScreen(
                taskId = id,
                repository = app.repository,
                onBack = { nav.popBackStack() },
            )
        }
        composable(
            Routes.TaskCreate,
            arguments = listOf(navArgument("projectId") { type = NavType.IntType }),
        ) { entry ->
            val projectId = entry.arguments?.getInt("projectId") ?: return@composable
            CreateTaskScreen(
                projectId = projectId,
                repository = app.repository,
                onBack = { nav.popBackStack() },
                onCreated = { taskId ->
                    nav.popBackStack()
                    nav.navigate(Routes.task(taskId))
                },
            )
        }
    }

    LaunchedEffect(pendingLink, paired) {
        if (!pendingLink.isNullOrBlank() && paired) {
            nav.navigate(Routes.Pairing)
        }
    }
}
