var Kora = Kora || {};
Kora.Projects = Kora.Projects || {};

Kora.Projects.Create = function() {

  $('.multi-select').chosen({
    width: '100%',
  });

  function scrollTop (allScrolls) {
    var scrollTo = Math.min(...allScrolls);
    var scrollTo = scrollTo - 100;
    setTimeout( function () {
      $('html, body').animate({
        scrollTop: 0
      }, 500);
    });
  }

  function initializeValidation() {
    $('.validate-project-js').on('click', function(e) {
      var $this = $(this);

      e.preventDefault();

      values = {};
      $.each($('.create-form').serializeArray(), function(i, field) {
        values[field.name] = field.value;
      });

      $.ajax({
        url: validationUrl,
        method: 'POST',
        data: values,
        success: function(data) {
          $('.create-form').submit();
        },
        error: function(err) {
//console.log('error - submitted'); apply verification fix here
          $('.error-message').text('');
          $('.text-input, .text-area').removeClass('error');
          var allScrolls = [];

          $.each(err.responseJSON.errors, function(fieldName, errors) {
            var $field = $('#'+fieldName);
            $field.addClass('error');
            $field.siblings('.error-message').text(errors[0]);
            allScrolls.push($field.offset().top);
          });

          scrollTop(allScrolls);
        }
      });
    });

    $('.text-input, .text-area').on('blur', function(e) {
      var field = this.id;
      var values = {};
      values[field] = this.value;
      values['_token'] = CSRFToken;

      $.ajax({
        url: validationUrl,
        method: 'POST',
        data: values,
        error: function(err) {
          if (err.responseJSON.errors[field] !== undefined) {
            $('#'+field).addClass('error');
            $('#'+field).siblings('.error-message').text(err.responseJSON.errors[field][0]);
          } else {
            $('#'+field).removeClass('error');
            $('#'+field).siblings('.error-message').text('');
          }
        }
      });
    });
  }

  function multiSelectPlaceholders () {
	  var inputDef = $('.chosen-container').children('.chosen-choices');
    var childCheck = inputDef.siblings('.chosen-drop').children('.chosen-results');
	  
	  inputDef.on('click', function() {
		  if (childCheck.children().length === 0) {
			  childCheck.append('<li class="no-results">No options to select!</li>');
		  } else if (childCheck.children('.active-result').length === 0 && childCheck.children('.no-results').length === 0) {
			  childCheck.append('<li class="no-results">No more options to select!</li>');
		  }
	  });
  }

  initializeValidation();
  multiSelectPlaceholders();
}
