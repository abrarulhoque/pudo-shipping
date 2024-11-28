jQuery(document).ready(function ($) {
  // Handle PUDO point selection in admin
  var pudoPointSelector = {
    init: function () {
      this.bindEvents()
    },

    bindEvents: function () {
      $(document).on(
        'click',
        '#generate-pudo-label',
        this.handleLabelGeneration
      )
    },

    handleLabelGeneration: function (e) {
      e.preventDefault()
      var $button = $(this)
      var $message = $('#pudo-label-message')

      $button.prop('disabled', true)
      $message.html('Generating label...')

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'generate_pudo_label',
          order_id: $button.data('order-id'),
          nonce: $button.data('nonce')
        },
        success: function (response) {
          if (response.success) {
            $message.html(
              '<div class="notice notice-success"><p>' +
                response.data.message +
                '</p></div>'
            )
            location.reload()
          } else {
            $message.html(
              '<div class="notice notice-error"><p>' +
                response.data.message +
                '</p></div>'
            )
            $button.prop('disabled', false)
          }
        },
        error: function () {
          $message.html(
            '<div class="notice notice-error"><p>An error occurred while generating the label.</p></div>'
          )
          $button.prop('disabled', false)
        }
      })
    }
  }

  // Initialize admin functionality
  pudoPointSelector.init()
})
