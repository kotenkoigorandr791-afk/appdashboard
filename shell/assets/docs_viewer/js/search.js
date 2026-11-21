function toggleSearch() {
    document.getElementById('searchBox').classList.add('expanded');
    document.getElementById('searchDocs').focus();
}

function collapseSearch() {
    document.getElementById('searchBox').classList.remove('expanded');
}

function filterDocs() {
    var query = document.getElementById('searchDocs').value.toLowerCase();
    var tiles = document.querySelectorAll('.doc-tile');
    tiles.forEach(function(tile) {
        var filename = tile.getAttribute('data-filename');
        var title = tile.querySelector('.doc-tile-title').textContent.toLowerCase();
        var desc = tile.querySelector('.doc-tile-desc').textContent.toLowerCase();
        var match = title.includes(query) || desc.includes(query) || filename.toLowerCase().includes(query);
        tile.style.display = match ? 'flex' : 'none';
    });
}
