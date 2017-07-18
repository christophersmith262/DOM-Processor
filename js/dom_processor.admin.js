(function($, Drupal) {
  'use strict';

  Drupal.behaviors.dom_processor_admin = {
    attach: function(context) {
      function processPluginSelect() {
        var pluginSet = $(this).attr('data-plugin-type')
        var selectedPlugin = $(this).attr('value');
        var $target = $(this).closest('.dom-processor-plugin-container').find('.' + pluginSet + '-order-' + selectedPlugin);
        if ($(this).is(':checked')) {
          $target.show();
        }
        else {
          $target.hide();
        }
      }
      $('.dom-processor-plugin-selection', context)
        .change(processPluginSelect)
        .each(processPluginSelect);
      $('.dom-processor-variant-group').each(function() {
        var $variantGroup = $(this);
        var $title = $(this).find('summary').first();
        $(this).find('.dom-processor-variant-group__label').keyup(function() {
          $title.html($(this).prop('value'));
        });
      });
    }
  };
}(jQuery, Drupal));
