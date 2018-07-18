var Kora = Kora || {};
Kora.Records = Kora.Records || {};

Kora.Records.Modal = function() {
  function initializeDesignateRecordPreset() {
      Kora.Modal.initialize();

      $('.designate-preset-js').click(function (e) {
          e.preventDefault();

          var $modal = $('.designate-record-preset-modal-js');

          Kora.Modal.open($modal);
      });

      $('.create-record-preset-js').click(function (e) {
          e.preventDefault();

          var preset_name = $('.preset-name-js').val();

          if(preset_name.length > 3) {
              $.ajax({
                  url: makeRecordPresetURL,
                  type: 'POST',
                  data: {
                      "_token": csrfToken,
                      "name": preset_name,
                      "rid": ridForPreset
                  },
                  success: function () {
                      var presetLink = $('.designate-preset-js');

                      presetLink.text('Designated as Preset');
                      presetLink.removeClass('designate-preset-js');
                      presetLink.unbind('click');
                      presetLink.addClass('already-preset-js');

                      location.reload();
                  }
              });
          } else {
              //TODO::error
          }
      });
  }

  function initializeAlreadyRecordPreset() {
      $('.already-preset-js').click(function (e) {
          e.preventDefault();

          var $modal = $('.already-record-preset-modal-js');

          Kora.Modal.open($modal);
      });

      $('.gotchya-js').click(function (e) {
          e.preventDefault();

          var $modal = $('.already-record-preset-modal-js');

          Kora.Modal.close($modal);
      });
  }

  initializeDesignateRecordPreset();
  initializeAlreadyRecordPreset();
};
