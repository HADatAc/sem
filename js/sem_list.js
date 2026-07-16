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

/* Infinite Scroll */
(function ($, Drupal) {
  Drupal.behaviors.semInfiniteScroll = {
    attach: function (context, settings) {
      if (window.semListInfiniteScrollInitialized) {
        return;
      }
      window.semListInfiniteScrollInitialized = true;

      let isLoading = false;
      const pageSizeIncrement = 9;

      function getAjaxErrorToastHost() {
        const hostId = 'sem-ajax-error-toast-host';
        let $host = $('#' + hostId);
        if ($host.length) {
          return $host;
        }

        $host = $('<div/>', { id: hostId }).css({
          position: 'fixed',
          right: '16px',
          bottom: '16px',
          width: 'min(420px, calc(100vw - 24px))',
          zIndex: 20000,
          display: 'flex',
          flexDirection: 'column',
          gap: '10px',
          pointerEvents: 'none'
        });
        $('body').append($host);
        return $host;
      }

      function showAjaxErrorToast(message) {
        const $host = getAjaxErrorToastHost();
        const $toast = $('<div/>').css({
          pointerEvents: 'auto',
          background: '#fff4f4',
          border: '1px solid #f1b8b8',
          borderLeft: '4px solid #d93f3f',
          color: '#7b1f1f',
          borderRadius: '8px',
          boxShadow: '0 10px 24px rgba(0, 0, 0, 0.16)',
          padding: '10px 12px',
          fontSize: '13px',
          lineHeight: '1.4',
          position: 'relative'
        });

        const $close = $('<button/>', {
          type: 'button',
          'aria-label': Drupal.t('Close'),
          text: 'x'
        }).css({
          position: 'absolute',
          top: '6px',
          right: '8px',
          border: 'none',
          background: 'transparent',
          color: '#7b1f1f',
          fontWeight: 700,
          fontSize: '14px',
          cursor: 'pointer',
          padding: 0,
          lineHeight: 1
        });

        const $content = $('<div/>').text(message).css({ paddingRight: '18px' });

        $close.on('click', function () {
          $toast.remove();
        });

        $toast.append($close).append($content);
        $host.append($toast);

        setTimeout(function () {
          $toast.fadeOut(180, function () {
            $toast.remove();
          });
        }, 7000);
      }

      function debounce(func, wait) {
        let timeout;
        return function () {
          clearTimeout(timeout);
          timeout = setTimeout(() => func.apply(this, arguments), wait);
        };
      }

      function onScroll() {
        const scrollThreshold = 20;
        const loadState = $("#list_state").val();

        // Se há mais itens para carregar e estamos perto do final da página
        if (loadState == 1 && $(window).scrollTop() + $(window).height() >= $(document).height() - scrollThreshold && !isLoading) {
          isLoading = true;
          $('#loading-overlay').show();
          $('#load-more-button').trigger('click'); // Dispara o clique no botão "Load More"
        }
      }

      // Quando o carregamento é concluído, esconder o indicador e liberar para próximo carregamento
      $(document).ajaxComplete(function () {
        $('#loading-overlay').hide();
        isLoading = false;
      });

      $(document).ajaxError(function (event, xhr) {
        $('#loading-overlay').hide();
        isLoading = false;
        const statusInfo = xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '';
        showAjaxErrorToast('Could not load more results. Please try again.' + statusInfo);
      });

      // Bind debounce to scroll
      $(window).on('scroll', debounce(onScroll, 50));
    }
  };
})(jQuery, Drupal);

