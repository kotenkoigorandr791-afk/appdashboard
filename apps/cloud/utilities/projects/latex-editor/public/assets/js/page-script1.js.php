    // Ожидаем загрузку PDF.js
    function waitForPDFJS() {
      return new Promise((resolve) => {
        if (typeof pdfjsLib !== 'undefined') {
          pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
          resolve();
        } else {
          setTimeout(() => waitForPDFJS().then(resolve), 50);
        }
      });
    }
    
    waitForPDFJS().then(() => {
      // PDF.js is ready
    });
  