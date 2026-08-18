/**
 * SimpleMarkdown.js - A lightweight markdown parser
 * Custom implementation for documentation viewer
 * Supports: headers, lists, links, code blocks, tables, blockquotes, bold, italic
 */
(function(global) {
    'use strict';

    function slugifyHeading(text) {
        return String(text || '')
            .replace(/^\uFEFF/, '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    var SimpleMarkdown = {
        version: '1.0.0',

        /**
         * Parse markdown text to HTML
         * @param {string} markdown - The markdown text to parse
         * @returns {string} - HTML output
         */
        parse: function(markdown) {
            if (!markdown) return '';

            var lines = markdown.split('\n');
            var html = [];
            var inCodeBlock = false;
            var codeBlockLang = '';
            var codeBlockContent = [];
            var inList = false;
            var listType = 'ul';
            var listIndent = 0;
            var inTable = false;
            var tableHeader = false;
            var tableRows = [];
            var tableHeaders = [];
            var blockquoteLines = [];
            var inBlockquote = false;

            function escapeHtml(text) {
                return text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function parseInline(text) {
                // Bold **text** or __text__
                text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                text = text.replace(/__(.+?)__/g, '<strong>$1</strong>');

                // Italic *text* or _text_
                text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');
                text = text.replace(/_(.+?)_/g, '<em>$1</em>');

                // Inline code `code`
                text = text.replace(/`([^`]+)`/g, '<code class="inline-code">$1</code>');

                // Links [text](url)
                text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="md-link" data-link="$2">$1</a>');

                // Images ![alt](url)
                text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" class="md-image">');

                return text;
            }

            function flushBlockquote() {
                if (inBlockquote && blockquoteLines.length > 0) {
                    html.push('<blockquote class="md-blockquote">');
                    blockquoteLines.forEach(function(line) {
                        html.push('<p>' + parseInline(line) + '</p>');
                    });
                    html.push('</blockquote>');
                    blockquoteLines = [];
                    inBlockquote = false;
                }
            }

            function flushList() {
                if (inList) {
                    html.push('</' + listType + '>');
                    inList = false;
                    listType = 'ul';
                }
            }

            function flushTable() {
                if (inTable && tableHeaders.length > 0) {
                    html.push('<table class="md-table">');
                    html.push('<thead><tr>');
                    tableHeaders.forEach(function(h) {
                        html.push('<th>' + parseInline(h) + '</th>');
                    });
                    html.push('</tr></thead>');
                    if (tableRows.length > 0) {
                        html.push('<tbody>');
                        tableRows.forEach(function(row) {
                            html.push('<tr>');
                            row.forEach(function(cell) {
                                html.push('<td>' + parseInline(cell) + '</td>');
                            });
                            html.push('</tr>');
                        });
                        html.push('</tbody>');
                    }
                    html.push('</table>');
                    inTable = false;
                    tableHeaders = [];
                    tableRows = [];
                }
            }

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i];
                var trimmedLine = line.trim();

                // Code blocks ```lang ... ```
                if (trimmedLine.match(/^```(\w*)$/)) {
                    if (!inCodeBlock) {
                        flushList();
                        flushTable();
                        flushBlockquote();
                        inCodeBlock = true;
                        codeBlockLang = trimmedLine.match(/^```(\w*)$/)[1] || '';
                        codeBlockContent = [];
                    } else {
                        html.push('<pre class="md-code-block"><code class="language-' + codeBlockLang + '">' +
                            escapeHtml(codeBlockContent.join('\n')) + '</code></pre>');
                        inCodeBlock = false;
                        codeBlockLang = '';
                    }
                    continue;
                }

                if (inCodeBlock) {
                    codeBlockContent.push(line);
                    continue;
                }

                // Empty line
                if (trimmedLine === '') {
                    flushList();
                    flushTable();
                    flushBlockquote();
                    continue;
                }

                // Headers # ## ### ####
                var headerMatch = trimmedLine.match(/^(#{1,6})\s+(.+)$/);
                if (headerMatch) {
                    flushList();
                    flushTable();
                    flushBlockquote();
                    var level = headerMatch[1].length;
                    var headerText = parseInline(headerMatch[2]);
                    var id = slugifyHeading(headerMatch[2]);
                    html.push('<h' + level + ' class="md-header" id="' + id + '">' + headerText + '</h' + level + '>');
                    continue;
                }

                // Horizontal rule --- or *** or ___
                if (trimmedLine.match(/^(-{3,}|\*{3,}|_{3,})$/)) {
                    flushList();
                    flushTable();
                    flushBlockquote();
                    html.push('<hr class="md-hr">');
                    continue;
                }

                // Blockquote > text
                if (trimmedLine.match(/^>\s*(.*)$/)) {
                    flushList();
                    flushTable();
                    inBlockquote = true;
                    blockquoteLines.push(trimmedLine.replace(/^>\s*/, ''));
                    continue;
                }

                // Unordered list - or *
                var ulMatch = trimmedLine.match(/^(\s*)[-*+]\s+(.+)$/);
                if (ulMatch) {
                    flushTable();
                    flushBlockquote();
                    if (!inList || listType !== 'ul') {
                        flushList();
                        inList = true;
                        listType = 'ul';
                        html.push('<ul class="md-list">');
                    }
                    html.push('<li>' + parseInline(ulMatch[2]) + '</li>');
                    continue;
                }

                // Ordered list 1. 2. 3.
                var olMatch = trimmedLine.match(/^(\s*)\d+\.\s+(.+)$/);
                if (olMatch) {
                    flushTable();
                    flushBlockquote();
                    if (!inList || listType !== 'ol') {
                        flushList();
                        inList = true;
                        listType = 'ol';
                        html.push('<ol class="md-list">');
                    }
                    html.push('<li>' + parseInline(olMatch[2]) + '</li>');
                    continue;
                }

                // Table row | cell | cell |
                if (trimmedLine.match(/^\|.*\|$/)) {
                    flushList();
                    flushBlockquote();
                    var cells = trimmedLine.split('|').filter(function(cell, idx) {
                        return idx > 0 && cell.trim() !== ''; // Skip first empty and trim
                    });
                    cells = cells.map(function(cell) { return cell.trim(); });

                    // Check if separator line |---|---|
                    if (cells.every(function(cell) { return cell.match(/^[-:]+$/); })) {
                        tableHeader = true;
                        continue;
                    }

                    if (!inTable) {
                        inTable = true;
                        tableHeaders = cells;
                        tableHeader = false;
                    } else if (tableHeader) {
                        tableHeader = false;
                    } else {
                        tableRows.push(cells);
                    }
                    continue;
                }

                // Regular paragraph
                flushList();
                flushTable();
                flushBlockquote();
                html.push('<p class="md-paragraph">' + parseInline(trimmedLine) + '</p>');
            }

            // Flush any remaining content
            if (inCodeBlock) {
                html.push('<pre class="md-code-block"><code class="language-' + codeBlockLang + '">' +
                    escapeHtml(codeBlockContent.join('\n')) + '</code></pre>');
            }
            flushList();
            flushTable();
            flushBlockquote();

            return html.join('\n');
        },

        /**
         * Extract table of contents from markdown
         * @param {string} markdown - The markdown text
         * @returns {Array} - Array of {level, text, id} objects
         */
        extractTOC: function(markdown) {
            var toc = [];
            var lines = markdown.split('\n');
            for (var i = 0; i < lines.length; i++) {
                var line = String(lines[i] || '').replace(/^\uFEFF/, '').trim();
                var match = line.match(/^(#{1,6})\s+(.+)$/);
                if (match) {
                    var level = match[1].length;
                    var text = match[2].trim();
                    var id = slugifyHeading(text);
                    toc.push({ level: level, text: text, id: id });
                }
            }
            return toc;
        },

        /**
         * Estimate reading time in minutes
         * @param {string} markdown - The markdown text
         * @returns {number} - Minutes to read
         */
        estimateReadingTime: function(markdown) {
            var words = markdown.trim().split(/\s+/).length;
            return Math.ceil(words / 200); // Average 200 words per minute
        }
    };

    // Export for different module systems
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = SimpleMarkdown;
    } else if (typeof define === 'function' && define.amd) {
        define(function() { return SimpleMarkdown; });
    } else {
        global.SimpleMarkdown = SimpleMarkdown;
    }

})(typeof window !== 'undefined' ? window : this);
