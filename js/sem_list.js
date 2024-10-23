(function ($, Drupal) {
    Drupal.behaviors.customTable = {
      attach: function (context, settings) {
        $(document).ready(function () {

          // Remove row
          $(document).on('click', '.remove-row', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();
          });

        });
      }
    };
})(jQuery, Drupal);
  