package orbit.nex.finance.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.LinkAnnotation
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.withLink
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp

/**
 * Minimal GitHub-flavoured markdown renderer used for release notes. It covers what
 * GitHub generates for release bodies: headings, bullet / ordered lists, bold, inline
 * code, links and fenced code blocks. Raw markdown markers never reach the screen.
 */
object ReleaseNotesMarkdown {

    sealed interface Block {
        data class Heading(val level: Int, val text: String) : Block
        data class Bullet(val depth: Int, val text: String) : Block
        data class Ordered(val marker: String, val depth: Int, val text: String) : Block
        data class Paragraph(val text: String) : Block
        data class Quote(val text: String) : Block
        data class Code(val text: String) : Block
        data object Divider : Block
    }

    private val HEADING = Regex("^(#{1,6})\\s+(.*)$")
    private val BULLET = Regex("^(\\s*)[-*+]\\s+(.*)$")
    private val ORDERED = Regex("^(\\s*)(\\d{1,3}[.)])\\s+(.*)$")
    private val QUOTE = Regex("^>\\s?(.*)$")
    private val DIVIDER = Regex("^(-{3,}|\\*{3,}|_{3,})$")
    private val FULL_CHANGELOG =
        Regex("^\\*{0,2}Full Changelog\\*{0,2}:?\\s*(https?://\\S+)\\s*$", RegexOption.IGNORE_CASE)
    private val NEW_CONTRIBUTORS =
        Regex("^#{1,6}\\s+New Contributors\\b.*$", RegexOption.IGNORE_CASE)

    fun parse(markdown: String?): List<Block> {
        if (markdown.isNullOrBlank()) return emptyList()
        val lines = markdown.replace("\r\n", "\n").replace('\r', '\n').lines()
        val blocks = mutableListOf<Block>()
        val paragraph = mutableListOf<String>()
        val code = StringBuilder()
        var inCode = false

        fun flushParagraph() {
            if (paragraph.isEmpty()) return
            blocks += Block.Paragraph(paragraph.joinToString(" ").trim())
            paragraph.clear()
        }

        for (raw in lines) {
            val line = raw.trimEnd()
            if (line.trimStart().startsWith("```")) {
                if (inCode) {
                    blocks += Block.Code(code.toString().trimEnd())
                    code.clear()
                    inCode = false
                } else {
                    flushParagraph()
                    inCode = true
                }
                continue
            }
            if (inCode) {
                code.appendLine(line)
                continue
            }
            val trimmed = line.trim()
            if (NEW_CONTRIBUTORS.matches(trimmed)) break
            when {
                trimmed.isEmpty() -> flushParagraph()
                DIVIDER.matches(trimmed) -> {
                    flushParagraph()
                    blocks += Block.Divider
                }
                HEADING.matches(trimmed) -> {
                    flushParagraph()
                    val match = HEADING.matchEntire(trimmed) ?: continue
                    blocks += Block.Heading(match.groupValues[1].length, match.groupValues[2].trim())
                }
                FULL_CHANGELOG.matches(trimmed) -> {
                    flushParagraph()
                    val url = FULL_CHANGELOG.matchEntire(trimmed)?.groupValues?.get(1) ?: continue
                    blocks += Block.Paragraph("[Full changelog]($url)")
                }
                BULLET.matches(line) -> {
                    flushParagraph()
                    val match = BULLET.matchEntire(line) ?: continue
                    blocks += Block.Bullet(match.groupValues[1].length / 2, match.groupValues[2].trim())
                }
                ORDERED.matches(line) -> {
                    flushParagraph()
                    val match = ORDERED.matchEntire(line) ?: continue
                    blocks += Block.Ordered(
                        marker = match.groupValues[2],
                        depth = match.groupValues[1].length / 2,
                        text = match.groupValues[3].trim(),
                    )
                }
                QUOTE.matches(trimmed) -> {
                    flushParagraph()
                    val text = QUOTE.matchEntire(trimmed)?.groupValues?.get(1).orEmpty().trim()
                    if (text.isNotEmpty()) blocks += Block.Quote(text)
                }
                else -> paragraph += trimmed
            }
        }
        if (inCode && code.isNotEmpty()) blocks += Block.Code(code.toString().trimEnd())
        flushParagraph()
        return blocks.filterNot { it is Block.Paragraph && it.text.isBlank() }
    }

    private val INLINE = Regex(
        "`([^`]+)`" +
            "|\\[([^\\]]+)]\\(([^)\\s]+)\\)" +
            "|\\*\\*([^*]+)\\*\\*" +
            "|__([^_]+)__" +
            "|(https?://[^\\s)]+)",
    )

    fun inline(text: String, linkColor: Color, codeBackground: Color): AnnotatedString =
        buildAnnotatedString {
            var index = 0
            for (match in INLINE.findAll(text)) {
                if (match.range.first > index) append(text.substring(index, match.range.first))
                val groups = match.groupValues
                when {
                    groups[1].isNotEmpty() -> withStyle(
                        SpanStyle(fontFamily = FontFamily.Monospace, background = codeBackground),
                    ) { append(groups[1]) }
                    groups[2].isNotEmpty() -> withLink(LinkAnnotation.Url(groups[3])) {
                        withStyle(SpanStyle(color = linkColor, textDecoration = TextDecoration.Underline)) {
                            append(groups[2])
                        }
                    }
                    groups[4].isNotEmpty() -> withStyle(SpanStyle(fontWeight = FontWeight.SemiBold)) {
                        append(groups[4])
                    }
                    groups[5].isNotEmpty() -> withStyle(SpanStyle(fontWeight = FontWeight.SemiBold)) {
                        append(groups[5])
                    }
                    else -> withLink(LinkAnnotation.Url(groups[6].trimEnd('.', ',', ';', ':'))) {
                        withStyle(SpanStyle(color = linkColor, textDecoration = TextDecoration.Underline)) {
                            append(groups[6])
                        }
                    }
                }
                index = match.range.last + 1
            }
            if (index < text.length) append(text.substring(index))
        }
}

@Composable
fun ReleaseNotesBody(markdown: String?, modifier: Modifier = Modifier) {
    val blocks = remember(markdown) { ReleaseNotesMarkdown.parse(markdown) }
    if (blocks.isEmpty()) return
    val linkColor = MaterialTheme.colorScheme.primary
    val codeBackground = MaterialTheme.colorScheme.surfaceVariant
    val bodyColor = MaterialTheme.colorScheme.onSurfaceVariant

    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        blocks.forEach { block ->
            when (block) {
                is ReleaseNotesMarkdown.Block.Heading -> Text(
                    text = ReleaseNotesMarkdown.inline(block.text, linkColor, codeBackground),
                    style = when (block.level) {
                        1 -> MaterialTheme.typography.titleMedium
                        2 -> MaterialTheme.typography.titleSmall
                        else -> MaterialTheme.typography.labelLarge
                    },
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                is ReleaseNotesMarkdown.Block.Bullet -> Row(
                    modifier = Modifier.fillMaxWidth().padding(start = (block.depth * 12).dp),
                ) {
                    Box(
                        modifier = Modifier
                            .padding(top = 8.dp, end = 10.dp)
                            .size(4.dp)
                            .clip(CircleShape)
                            .background(linkColor.copy(alpha = 0.6f)),
                    )
                    Text(
                        text = ReleaseNotesMarkdown.inline(block.text, linkColor, codeBackground),
                        style = MaterialTheme.typography.bodyMedium,
                        color = bodyColor,
                        modifier = Modifier.weight(1f),
                    )
                }
                is ReleaseNotesMarkdown.Block.Ordered -> Row(
                    modifier = Modifier.fillMaxWidth().padding(start = (block.depth * 12).dp),
                ) {
                    Text(
                        text = block.marker,
                        style = MaterialTheme.typography.bodyMedium,
                        color = bodyColor,
                        modifier = Modifier.width(24.dp),
                    )
                    Text(
                        text = ReleaseNotesMarkdown.inline(block.text, linkColor, codeBackground),
                        style = MaterialTheme.typography.bodyMedium,
                        color = bodyColor,
                        modifier = Modifier.weight(1f),
                    )
                }
                is ReleaseNotesMarkdown.Block.Paragraph -> Text(
                    text = ReleaseNotesMarkdown.inline(block.text, linkColor, codeBackground),
                    style = MaterialTheme.typography.bodyMedium,
                    color = bodyColor,
                )
                is ReleaseNotesMarkdown.Block.Quote -> Text(
                    text = ReleaseNotesMarkdown.inline(block.text, linkColor, codeBackground),
                    style = MaterialTheme.typography.bodyMedium,
                    fontStyle = FontStyle.Italic,
                    color = bodyColor,
                    modifier = Modifier.padding(start = 12.dp),
                )
                is ReleaseNotesMarkdown.Block.Code -> Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    color = codeBackground,
                ) {
                    Text(
                        text = block.text,
                        style = MaterialTheme.typography.bodySmall.copy(fontFamily = FontFamily.Monospace),
                        color = MaterialTheme.colorScheme.onSurface,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                    )
                }
                ReleaseNotesMarkdown.Block.Divider -> HorizontalDivider(
                    color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f),
                )
            }
        }
    }
}
