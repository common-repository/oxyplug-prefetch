jQuery(function ($) {

  if ($('form#post #sample-permalink').length) {
    let post_name = $('form#post #sample-permalink > a').text().trim();
    $.each($('.oxy-prefetch-statics-wrap'), function (index, wrap) {
      const $strong = $(wrap).find('> h2 > strong');
      if ($strong.text().trim() != post_name) {
        $strong.text(post_name);
      }
    });
  }

  let is_typing = false;
  $('form#post').on('blur', '[name="post_title"]', function () {
    if ($('form#post #sample-permalink').length == 0) {
      if (is_typing == false) {
        is_typing = true;
        let got = setInterval(function () {
          if ($('form#post #sample-permalink').length) {
            const post_name = $('form#post #sample-permalink > a').text();
            $.each($('.oxy-prefetch-statics-wrap'), function (index, wrap) {
              $(wrap).find('> h2 > strong').text(post_name);
            });
            clearInterval(got);
            is_typing = false;
          }
        }, 3000);
      }
    }
  });

  $('.oxy-prefetch-has-tooltip').on('mouseenter', function () {
    const tooltip_text = $(this).data('tooltip').trim();
    if (tooltip_text.length > 0) {
      // Link
      let link = '';
      let href = $(this).data('href');
      let href_text = $(this).data('href-text');
      if (href && href_text) {
        link = ` <a href="${href.trim()}" target="_blank">${href_text.trim()}</a>`;
      }

      $(this).prepend('<div class="oxy-prefetch-tooltip">' + tooltip_text + link + '</div>');
      $(this).find('.oxy-prefetch-tooltip').css('top', $(this).height() + 5).fadeIn();
    }
  }).on('mouseleave', function () {
    $(this).find('.oxy-prefetch-tooltip').fadeOut(function () {
      $(this).remove();
    });
  });

  let empty_post_name = true;
  $(document).on('change', '#edit-slug-box', function () {
    if (empty_post_name) {
      let got = setInterval(function () {
        const post_name = $('#edit-slug-box #sample-permalink > a').text().trim();
        if (post_name) {
          $.each($('.oxy-prefetch-statics-wrap'), function (index, wrap) {
            $(wrap).find('> h2 > strong').text(post_name);
          });
          empty_post_name = false;
          clearInterval(got);
        }
      }, 3000);
    }
  });

  $(document).on('click', '#oxy-prefetch-settings-save', function (e) {
    e.preventDefault();

    if ($('.oxy-prefetch-invalid').length === 0) {
      const settings_form = $(this).closest('form');
      const disabled = settings_form.find(':input:disabled').removeAttr('disabled');
      const data = settings_form.serializeArray();
      disabled.attr('disabled', 'disabled');

      data.push({name: 'action', value: 'save_prefetch_settings'});
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: data,
        complete() {
          window.location.assign(window.location.href);
        },
      });
    }
  });

  $(document).on('change', '#oxy-prefetches-number-status, #oxy-prefetch-prerender-status', function () {
    const $this = $(this);
    const $next = $this.parent().next();
    $this.is(':checked') ? $next.removeAttr('disabled') : $next.attr('disabled', 'disabled');
  });

  $(document).on('change', '.oxy-prefetch-switcher [name="oxy_prefetch_status"], .oxy-prefetch-switcher [name="oxy_prefetch_prerender_status"]', function () {
    const $inputs_and_buttons = $(this).parent().parent().parent().find('input[name*="[prefetch_these]"], input[name*="[prerender_these]"], button');

    if ($(this).is(':checked')) {
      $.each($inputs_and_buttons, function (index, input_and_button) {
        $(input_and_button).removeAttr('disabled');
      });
    } else {
      $.each($inputs_and_buttons, function (index, input_and_button) {
        $(input_and_button).attr('disabled', 'disabled');
      });
    }
  });

  $(document).on('click', '.oxy-add-more-static-urls', function (e) {
    e.preventDefault();

    let new_url_div = $(this).prev().clone();
    let new_url_input = $(new_url_div).find('input');

    let new_url_input_name = $(new_url_input).attr('name');
    let new_url_input_name_number = new_url_input_name.replace(/\D/g, '');
    new_url_input_name_number = parseInt(new_url_input_name_number) + 1;
    const new_url_input_new_name_number = new_url_input_name.replace(/\d/g, new_url_input_name_number);

    $(new_url_input).attr('name', new_url_input_new_name_number).val('');
    let delete_button = $(new_url_div).find('.oxy-delete-parent');
    if (!delete_button.is(':visible')) {
      delete_button.show();
    }
    $(this).before(new_url_div);
  });

  $(document).on('click', '.oxy-delete-parent', function () {
    $(this).parent().remove();
  });

  $(document).on('click', '#oxy-prefetch-dismiss-prerender-notice', function (e) {
    e.preventDefault();
    $(this).parent().parent().slideUp();
    const data = [
      {name: 'action', value: 'dismiss_prerender_notice'},
      {name: 'oxy_prefetch_dismiss_prerender_notice_nonce', value: $(this).data('nonce')},
    ];
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'json',
      data: data,
      success(response) {
        console.log(response.data.message);
      },
      error(response) {
        console.log(response.responseJSON.data.message);
      },
    });
  });

  $(document).on('click', '.oxy-prefetch-add-exclusion', function (e) {
    e.preventDefault();
    const $exclusion_inputs = $(this).prev();
    const $new_div = $exclusion_inputs.find(' > div').first().clone();
    $new_div.find('input').val('');
    $new_div.append(
      `<button class="button oxy-prefetch-button-danger-outline oxy-prefetch-remove-exclusion">
        <i class="dashicons dashicons-trash"></i>
      </button>`
    );
    $exclusion_inputs.append($new_div);
  });

  $(document).on('click', '.oxy-prefetch-remove-exclusion', function (e) {
    e.preventDefault();
    $(this).parent().remove();
  });

  $(document).on('change', '#oxy-prefetch-prerender-href-exclusion-status, #oxy-prefetch-prerender-selector-exclusion-status', function () {
    const checked = $(this).is(':checked');
    const $inputs_and_buttons = $(this).parent().next().find(':input');
    $.each($inputs_and_buttons, function (index, input) {
      $(input).prop('disabled', !checked);
    });
  });

  const isSelectorValid = ((dummyElement) => (selector) => {
    try { dummyElement.querySelector(selector) } catch { return false }
    return true
  })(document.createDocumentFragment());

  $(document).on('input', '[name="oxy_prefetch_prerender_matches[selector][]"]', function () {
    const value = $(this).val().trim()
    if (value == '' || isSelectorValid(value)) {
      $(this).removeClass('oxy-prefetch-invalid');
    } else {
      $(this).addClass('oxy-prefetch-invalid');
    }
  });

});