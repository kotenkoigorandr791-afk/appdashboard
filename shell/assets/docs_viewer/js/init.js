window.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    var filename = urlParams.get('file');
    if (filename && docsData.some(function(d) { return d.filename === filename; })) {
        openDoc(filename);
    }
});

window.addEventListener('popstate', function(event) {
    if (event.state && event.state.file) {
        openDoc(event.state.file);
    } else {
        showGridView();
    }
});
