jQuery(document).ready(function ($) {
  var pudoCheckout = {
    init: function () {
      this.bindEvents()
      this.initializeMap()
    },

    bindEvents: function () {
      $(document.body).on('updated_checkout', this.onUpdatedCheckout.bind(this))
      $(document).on(
        'change',
        'input[name="pudo_point"]',
        this.onPudoPointSelect.bind(this)
      )
    },

    initializeMap: function () {
      // Initialize map if needed in future versions
    },

    onUpdatedCheckout: function () {
      var shippingMethod = $('input[name^="shipping_method"]:checked').val()

      if (shippingMethod && shippingMethod.includes('pudo')) {
        this.loadPudoPoints()
      }
    },

    loadPudoPoints: function () {
      var self = this
      var $container = $('#pudo-points-container')
      var $loading = $('#pudo-points-loading')
      var $list = $('#pudo-points-list')

      // Get shipping postcode
      var postcode =
        $('#shipping_postcode').val() || $('#billing_postcode').val()

      if (!postcode) {
        $list.html(
          '<p class="woocommerce-error">' +
            'Please enter a postal code to view available PUDO points.' +
            '</p>'
        )
        return
      }

      $loading.show()
      $list.html('')

      $.ajax({
        url: pudoCheckout.ajax_url,
        type: 'POST',
        data: {
          action: 'get_pudo_points',
          nonce: pudoCheckout.nonce,
          postal_code: postcode
        },
        success: function (response) {
          if (response.success && response.data.points) {
            self.displayPudoPoints(response.data.points)
          } else {
            $list.html(
              '<p class="woocommerce-error">' +
                (response.data.message ||
                  'No PUDO points found in your area.') +
                '</p>'
            )
          }
        },
        error: function () {
          $list.html(
            '<p class="woocommerce-error">' +
              'Error loading PUDO points. Please try again.' +
              '</p>'
          )
        },
        complete: function () {
          $loading.hide()
        }
      })
    },

    displayPudoPoints: function (points) {
      var $list = $('#pudo-points-list')
      var template = $('#pudo-point-template').html()
      var html = ''

      points.forEach(function (point) {
        var pointHtml = template
          .replace(/{dealer_id}/g, point.id)
          .replace('{name}', point.name)
          .replace('{address}', point.address)
          .replace('{city}', point.city)
          .replace('{state}', point.state)
          .replace('{postal_code}', point.postal_code)
          .replace('{distance}', point.distance)
          .replace('{checked}', points.length === 1 ? 'checked' : '')

        html += pointHtml
      })

      $list.html(html)

      // If only one point, select it automatically
      if (points.length === 1) {
        this.onPudoPointSelect()
      }
    },

    onPudoPointSelect: function () {
      var $selected = $('input[name="pudo_point"]:checked')
      if (!$selected.length) {
        return
      }

      var $point = $selected.closest('.pudo-point')
      var pointData = {
        id: $point.data('dealer-id'),
        name: $point.find('strong').text(),
        address: $point.find('label').contents().eq(2).text().trim(),
        city: $point.find('label').contents().eq(4).text().trim().split(',')[0],
        state: $point
          .find('label')
          .contents()
          .eq(4)
          .text()
          .trim()
          .split(',')[1]
          .split(' ')[1],
        postal_code: $point
          .find('label')
          .contents()
          .eq(4)
          .text()
          .trim()
          .split(' ')
          .pop()
      }

      // Store selected point data
      $('#selected_pudo_point').val(JSON.stringify(pointData))

      // Trigger checkout update
      $(document.body).trigger('update_checkout')
    }
  }

  // Initialize checkout functionality
  pudoCheckout.init()
})
