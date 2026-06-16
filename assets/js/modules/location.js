/**
 * Feature Module: Location APIs & Autocomplete Predictions
 */
jQuery(document).ready(function ($) {
  const params = kodyt_checkout_params;
  let debounceTimer = null;

  setupAutocompleteWatcher(
    "#kodyt_shipping_autocomplete",
    "#kodyt_shipping_suggestions",
    "shipping",
  );
  setupAutocompleteWatcher(
    "#kodyt_billing_autocomplete",
    "#kodyt_billing_suggestions",
    "billing",
  );

  function setupAutocompleteWatcher(inputSelector, boxSelector, prefix) {
    $(document).on("input", inputSelector, function () {
      let query = $(this).val();
      clearTimeout(debounceTimer);
      if (query.length < 3) return $(boxSelector).hide().empty();

      debounceTimer = setTimeout(function () {
        $.post(
          params.ajax_url,
          {
            action: "kodyt_proxy_autocomplete",
            security: params.checkout_nonce,
            q: query,
          },
          function (response) {
            if (
              response.success &&
              response.data &&
              response.data.suggestions
            ) {
              let suggestions = response.data.suggestions;
              let html = "";
              suggestions.forEach(function (item) {
                if (item?.placePrediction) {
                  let pred = item.placePrediction;
                  if (pred.text?.text && pred.placeId) {
                    html += `<div class="kodyt-suggestion-item" data-id="${pred.placeId}" data-text="${pred.text.text}">${pred.text.text}</div>`;
                  }
                }
              });
              if (html !== "") $(boxSelector).html(html).show();
              else $(boxSelector).hide().empty();
            }
          },
          "json",
        );
      }, 300);
    });

    $(document).on(
      "click",
      boxSelector + " .kodyt-suggestion-item",
      function () {
        let selectedId = $(this).data("id");
        let selectedText = $(this).data("text");
        $(inputSelector).val(selectedText);
        $(boxSelector).hide().empty();

        $.post(
          params.ajax_url,
          {
            action: "kodyt_proxy_validate_address",
            security: params.checkout_nonce,
            place_id: selectedId,
            text: selectedText,
          },
          function (response) {
            if (response.success) {
              let addr = response.data?.address || response.data;
              if (addr.street) $(inputSelector).val(addr.street);
              if (addr.house_number || addr.building_number)
                $(`#kodyt_${prefix}_house_number`).val(
                  addr.house_number || addr.building_number,
                );
              if (addr.city) $(`#kodyt_${prefix}_city`).val(addr.city);
              let postcode =
                addr.postcode || addr.zip || addr.postal_code || "";
              if (postcode) $(`#kodyt_${prefix}_postcode`).val(postcode);
              if (addr.country_code || addr.country)
                $(`#kodyt_${prefix}_country`).val(
                  addr.country_code || addr.country,
                );
            }
          },
          "json",
        );
      },
    );
  }

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".kodyt-autocomplete-wrapper").length) {
      $(".kodyt-suggestions-box").hide();
    }
  });
});
