(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var select = event.target.closest('[data-hub-media-select]');
    var remove = event.target.closest('[data-hub-media-remove]');
    var button = select || remove;

    if (!button) {
      return;
    }

    var field = button.closest('[data-hub-media-field]');
    if (!field) {
      return;
    }

    var input = field.querySelector('[data-hub-media-input]');
    var preview = field.querySelector('[data-hub-media-preview]');
    var removeButton = field.querySelector('[data-hub-media-remove]');

    if (!input || !preview || !removeButton) {
      return;
    }

    event.preventDefault();

    if (remove) {
      input.value = '0';
      preview.replaceChildren();
      removeButton.hidden = true;
      return;
    }

    if (!window.wp || !window.wp.media) {
      return;
    }

    var frame = window.wp.media({
      title: 'Seleccionar imagen',
      button: { text: 'Usar esta imagen' },
      library: { type: 'image' },
      multiple: false
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      var source = attachment.url || '';

      if (attachment.sizes && attachment.sizes.medium) {
        source = attachment.sizes.medium.url || source;
      }

      input.value = String(attachment.id || 0);
      preview.replaceChildren();

      if (source) {
        var image = document.createElement('img');
        image.src = source;
        image.alt = '';
        image.style.display = 'block';
        image.style.maxWidth = '220px';
        image.style.height = 'auto';
        image.style.borderRadius = '6px';
        preview.appendChild(image);
      }

      removeButton.hidden = !attachment.id;
    });

    frame.open();
  });
})();
