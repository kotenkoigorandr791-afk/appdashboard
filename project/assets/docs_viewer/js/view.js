function showGridView() {
    document.getElementById('docsGrid').classList.remove('hidden');
    document.getElementById('docView').classList.remove('active');
    document.getElementById('backToGridBtn').style.display = 'none';
    window.history.pushState({}, '', window.location.pathname);
}

function openDoc(filename) {
    fetch('?file=' + encodeURIComponent(filename))
        .then(function(response) {
            if (!response.ok) throw new Error('File not found');
            return response.json();
        })
        .then(function(data) {
            var markdown = data.content;
            var html = SimpleMarkdown.parse(markdown);
            document.getElementById('docContent').innerHTML = html;

            var firstLine = markdown.split('\n').find(function(line) {
                return line.startsWith('# ');
            });
            var title = firstLine ? firstLine.replace(/^#\s+/, '') : filename;
            document.getElementById('docViewTitle').textContent = title;

            var doc = docsData.find(function(d) { return d.filename === filename; });
            if (doc) {
                document.getElementById('docViewReadTime').textContent = doc.readTime + ' ' + t('minutes', 'min');
                document.getElementById('docViewModified').textContent = t('last_updated', 'Updated') + ': ' + new Date(doc.modified * 1000).toLocaleDateString();
            }

            var toc = SimpleMarkdown.extractTOC(markdown);
            var tocList = document.getElementById('docTOCList');
            tocList.innerHTML = '';
            toc.forEach(function(item) {
                var li = document.createElement('li');
                li.className = 'doc-toc-item doc-toc-level-' + item.level;
                var a = document.createElement('a');
                a.href = '#' + item.id;
                a.className = 'doc-toc-link';
                a.textContent = item.text;
                a.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById(item.id).scrollIntoView({ behavior: 'smooth' });
                };
                li.appendChild(a);
                tocList.appendChild(li);
            });

            document.getElementById('docsGrid').classList.add('hidden');
            document.getElementById('docView').classList.add('active');
            document.getElementById('backToGridBtn').style.display = 'inline-flex';
            
            var newUrl = window.location.pathname + '?file=' + encodeURIComponent(filename);
            window.history.pushState({file: filename}, '', newUrl);

            setupDocLinks();
        })
        .catch(function(error) {
            console.error('Error loading doc:', error);
            alert((translations.error_loading_documentation || 'Error loading documentation:') + ' ' + error.message);
        });
}
