package com.stepmonitor.app.releases

import android.content.Context
import com.stepmonitor.app.R
import java.io.BufferedReader

/**
 * Release notes bundled with the app (res/raw/release_notes.md).
 * Used as the primary source for the "What's new" window so users always
 * see the current changelog even when the remote GitHub repo has no matching tag.
 */
object LocalReleaseNotes {

    private val SECTION_HEADER = Regex("^##\\s+(.+?)(?:\\s+-\\s+([\\d-]+))?$")

    fun findReleaseForVersion(context: Context, versionName: String): GithubRelease? =
        load(context).firstOrNull { ReleaseNotesFormatter.matchesVersion(it.tagName, versionName) }

    private fun load(context: Context): List<GithubRelease> {
        val text = runCatching {
            val input = context.resources.openRawResource(R.raw.release_notes)
            input.bufferedReader(Charsets.UTF_8).use(BufferedReader::readText)
        }.getOrNull() ?: return emptyList()

        val releases = mutableListOf<GithubRelease>()
        var currentTag: String? = null
        var currentDate = ""
        val bodyLines = mutableListOf<String>()
        var haveContent = false

        fun flush() {
            val tag = currentTag ?: return
            if (haveContent || bodyLines.isNotEmpty()) {
                releases += GithubRelease(
                    tagName = tag,
                    name = "",
                    publishedAt = currentDate,
                    body = bodyLines.joinToString("\n").trim(),
                )
            }
        }

        text.replace("\r\n", "\n").replace("\r", "\n").lineSequence().forEach { rawLine ->
            val line = rawLine.trim()
            if (line.isEmpty()) return@forEach

            val header = SECTION_HEADER.find(line)
            if (header != null) {
                flush()
                currentTag = header.groupValues[1].trim()
                currentDate = header.groupValues[2].trim()
                bodyLines.clear()
                haveContent = false
            } else {
                if (currentTag == null) return@forEach
                haveContent = true
                bodyLines += line
            }
        }
        flush()

        return releases
    }
}