(function (Drupal, $, drupalSettings) {
  'use strict';

  Drupal.behaviors.semUnitDescription = {
    attach: function (context) {
      $('.sem-unit-autocomplete', context).each(function () {
        var $input = $(this);

        // Prevent multiple bindings on the same element.
        if ($input.data('sem-unit-desc-bound')) {
          return;
        }
        $input.data('sem-unit-desc-bound', true);

        // Find or create the description container placed after the field.
        var $wrapper = $input.closest('.js-form-item');
        var $desc = $wrapper.find('.sem-unit-description');

        if (!$desc.length) {
          // Fallback: create the container if suffix was not rendered for any reason.
          $desc = $('<div class="sem-unit-description description" style="margin-top:8px; margin-bottom:-4px; color:#666; font-size:9pt; padding:10px; border-radius:5px; background-color:#f0f0f0; display:none;"></div>');
          $wrapper.append($desc);
        }

        var maxLength = 220; // Max characters to show before truncation.
        var fullText = '';

        /**
         * Render the description text below the field.
         * - Shows "Description: ..."
         * - Truncates long text
         * - Adds a toggle link for "Show more / Show less" when needed.
         */
        function renderDescription(text) {
          $desc.empty();

          if (!text) {
            $desc.hide();
            return;
          }

          fullText = text;

          var truncated = text.length > maxLength
            ? text.substring(0, maxLength) + '…'
            : text;

          // Build DOM safely to avoid HTML injection.
          var $label = $('<strong/>').text('Description: ');
          var $span = $('<span class="sem-unit-description-text"/>').text(truncated);

          $desc.append($label).append($span);

          if (text.length > maxLength) {
            var $toggle = $('<a href="#" class="sem-unit-description-toggle"/>')
              .text(Drupal.t('Show more'))
              .data('expanded', false);

            $desc.append(' ').append($toggle);
          }

          $desc.show();
        }

        /**
         * Handle selection from Drupal core autocomplete.
         * ui.item should contain: label, value, description (if provided by the backend).
         */
        $input.on('autocompleteSelect autocompleteselect', function (event, ui) {
          if (!ui || !ui.item) {
            return;
          }

          var description = ui.item.description || '';
          renderDescription(description);
        });

        /**
         * Handle click on "Show more / Show less" toggle link.
         */
        $wrapper.on('click', '.sem-unit-description-toggle', function (e) {
          e.preventDefault();

          var $toggle = $(this);
          var $text = $desc.find('.sem-unit-description-text');
          var expanded = $toggle.data('expanded') === true;

          if (expanded) {
            // Collapse back to truncated text.
            var truncated = fullText.substring(0, maxLength) + '…';
            $text.text(truncated);
            $toggle.text(Drupal.t('Show more'));
            $toggle.data('expanded', false);
          } else {
            // Show full description text.
            $text.text(fullText);
            $toggle.text(Drupal.t('Show less'));
            $toggle.data('expanded', true);
          }
        });

        /**
         * Clear description when the user clears or manually changes the field.
         */
        $input.on('input', function () {
          if (!$input.val()) {
            $desc.empty().hide();
          }
        });
      });
    }
  };

})(Drupal, jQuery, drupalSettings);
