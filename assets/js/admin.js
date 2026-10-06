(function () {
  document.addEventListener('DOMContentLoaded', function () {
    initRichTextEditors();
    initFilePickers();
    initConfirmForms();
  });

  /**
   * Vraagt bevestiging voor formulieren met een data-confirm-attribuut
   * (bv. verwijderen). Vervangt inline onsubmit-handlers, die door de
   * Content-Security-Policy (zie admin/includes/auth.php) geblokkeerd worden.
   */
  function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    });
  }

  function initRichTextEditors() {
    if (typeof ClassicEditor === 'undefined') return;

    var editors = [];
    var textareas = document.querySelectorAll('textarea.rich-text');

    textareas.forEach(function (el) {
      ClassicEditor
        .create(el, {
          toolbar: [
            'heading', '|',
            'bold', 'italic', 'link', '|',
            'bulletedList', 'numberedList', 'blockQuote', '|',
            'imageUpload', 'mediaEmbed', '|',
            'undo', 'redo'
          ],
          image: {
            toolbar: ['imageTextAlternative', '|', 'imageStyle:alignLeft', 'imageStyle:full', 'imageStyle:alignRight']
          },
          mediaEmbed: {
            // Slaat de kant-en-klare embed (bv. de YouTube-iframe) zelf op in de
            // inhoud, zodat de publieke pagina dit kan tonen zonder CKEditor.
            previewsInData: true
          }
        })
        .then(function (editor) {
          editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
            return new CkeditorUploadAdapter(loader, el);
          };
          editors.push(editor);
        })
        .catch(function (err) {
          console.error('CKEditor kon niet worden geladen:', err);
        });
    });

    if (!textareas.length) return;

    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        editors.forEach(function (editor) {
          editor.updateSourceElement();
        });
      });
    });
  }

  /**
   * Uploadadapter voor CKEditor-afbeeldingen: stuurt het bestand naar
   * admin/upload_image.php en levert de URL terug die CKEditor in de
   * inhoud opneemt. Zie https://ckeditor.com/docs/ckeditor5/latest/framework/deep-dive/upload-adapter.html
   */
  function CkeditorUploadAdapter(loader, textareaEl) {
    this.loader = loader;
    this.textareaEl = textareaEl;
  }

  CkeditorUploadAdapter.prototype.upload = function () {
    var textareaEl = this.textareaEl;
    return this.loader.file.then(function (file) {
      return new Promise(function (resolve, reject) {
        var form = textareaEl.form;
        var tokenField = form ? form.querySelector('input[name="csrf_token"]') : null;
        var formData = new FormData();
        formData.append('upload', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload_image.php', true);
        xhr.responseType = 'json';
        if (tokenField) {
          xhr.setRequestHeader('X-CSRF-Token', tokenField.value);
        }
        xhr.addEventListener('load', function () {
          var response = xhr.response;
          if (!response || response.error) {
            reject((response && response.error && response.error.message) || 'Upload mislukt.');
            return;
          }
          resolve({ default: response.url });
        });
        xhr.addEventListener('error', function () {
          reject('Upload mislukt (netwerkfout).');
        });
        xhr.addEventListener('abort', function () {
          reject();
        });
        xhr.send(formData);
      });
    });
  };

  CkeditorUploadAdapter.prototype.abort = function () {};

  /**
   * Vult een tekstveld met het pad van een bestaand geüpload bestand,
   * zodat je dat pad niet zelf hoeft over te typen. Gekoppeld via
   * <select class="file-picker" data-target="id-van-tekstveld">.
   */
  function initFilePickers() {
    document.querySelectorAll('select.file-picker').forEach(function (select) {
      select.addEventListener('change', function () {
        if (!this.value) return;
        var target = document.getElementById(this.getAttribute('data-target'));
        if (target) {
          target.value = this.value;
          target.dispatchEvent(new Event('input'));
        }
        this.selectedIndex = 0;
      });
    });
  }
})();
