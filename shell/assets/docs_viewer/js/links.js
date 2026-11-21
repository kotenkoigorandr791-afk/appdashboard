function setupDocLinks() {
    var content = document.getElementById('docContent');
    var links = content.querySelectorAll('a');
    
    links.forEach(function(link) {
        var href = link.getAttribute('href');
        if (!href) return;
        
        if (href.endsWith('.md')) {
            link.onclick = function(e) {
                e.preventDefault();
                var targetFile = href.replace(/^\.?\//, '');
                var doc = docsData.find(function(d) { return d.filename === targetFile; });
                if (doc) {
                    openDoc(targetFile);
                } else {
                    var partialMatch = docsData.find(function(d) { return d.filename.includes(targetFile); });
                    if (partialMatch) {
                        openDoc(partialMatch.filename);
                    } else {
                        window.open(href, '_blank');
                    }
                }
            };
        }
        else if (href.startsWith('#')) {
            link.onclick = function(e) {
                e.preventDefault();
                var targetId = href.substring(1);
                var targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            };
        }
    });
}
