/**
 * Root Controller Orchestrator
 */
jQuery(document).ready(function ($) {
  const params = kodyt_checkout_params;
  let pinDelayTimeout = null;

  // Inline Accordion Slide Toggle Engine mapping rules
  $(document).on("click", "#kodyt-summary-toggle-bar", function (e) {
    e.preventDefault();
    const panel = $("#kodyt-summary-dropdown-panel");
    const arrow = $(this).find(".kodyt-summary-arrow");

    panel.stop(true, true).slideToggle(200, function () {
      if (panel.is(":visible")) {
        arrow.text("▲");
      } else {
        arrow.text("▼");
      }
    });
  });

  // Synchronize applied coupon values
  $(document).ajaxSuccess(function (event, xhr, settings) {
    if (
      settings.data &&
      (settings.data.indexOf("action=kodyt_apply_checkout_coupon") !== -1 ||
        settings.data.indexOf("action=kodyt_remove_checkout_coupon") !== -1)
    ) {
      if (xhr.responseJSON && xhr.responseJSON.success) {
        const updatedTotal = xhr.responseJSON.data.grandtotal;
        if (updatedTotal) {
          $("#kodyt-toggle-bar-grandtotal").html(updatedTotal);
        }
      }
    }
  });

  /* ==========================================================
     KODYT DYNAMIC INLINE PINCODE VALIDATION SYSTEM (BACKEND AJAX)
  ========================================================== */
  function updatePincodeStatusMessage(fieldElement, status, textMessage) {
    if (!fieldElement || fieldElement.length === 0) return;
    const feedbackClass = "kodyt-pin-msg-" + fieldElement.attr("id");
    $("." + feedbackClass).remove();

    let styles =
      "font-size: 13px; margin-top: 5px; display: block; font-weight: 500; transition: all 0.2s ease;";
    if (status === "success") styles += " color: #00b074;";
    else if (status === "error") styles += " color: #dc2626;";
    else styles += " color: #6b7280;";

    fieldElement.after(
      '<span class="' +
        feedbackClass +
        '" style="' +
        styles +
        '">' +
        textMessage +
        "</span>",
    );
  }

  $(document).on("change", "#kodyt_shipping_postcode", function () {
    const currentField = $(this);
    const pincodeValue = currentField.val().trim();

    if (pincodeValue.length === 0) {
      $(".kodyt-pin-msg-" + currentField.attr("id")).remove();
      return;
    }
    if (pincodeValue.length < 6) {
      updatePincodeStatusMessage(
        currentField,
        "pending",
        "✍️ Typing pincode...",
      );
      return;
    }

    clearTimeout(pinDelayTimeout);
    pinDelayTimeout = setTimeout(function () {
      updatePincodeStatusMessage(currentField, "pending", "🔍 Checking...");
      $.post(
        params.ajax_url,
        {
          action: "kodyt_validate_pincode",
          pincode: pincodeValue,
        },
        function (response) {
          if (
            response &&
            (response.success === true || response.success === "true")
          ) {
            updatePincodeStatusMessage(
              currentField,
              "success",
              `✅ Pincode is valid`,
            );
          } else {
            updatePincodeStatusMessage(
              currentField,
              "error",
              "❌ Pincode is not valid / serviceable",
            );
          }
        },
        "json",
      );
    }, 500);
  });

  // Form final submit checkout loop orchestrator
  $("#kodyt-custom-checkout-form").on("submit", function (e) {
    e.preventDefault();
    let submitBtn = $("#kodyt-btn-place-order")
      .text("Processing Order...")
      .prop("disabled", true);

    let authDialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "91";
    let shippingDialCode = window.kodytShippingItiInstance
      ? window.kodytShippingItiInstance.getSelectedCountryData().dialCode
      : authDialCode;

    let verifiedAuthMobile = $("#kodyt_auth_phone").val() || "";
    let shippingMobileValue = $("#kodyt_shipping_phone").val()
      ? $("#kodyt_shipping_phone").val().trim()
      : verifiedAuthMobile;

    let searchParams = new URLSearchParams($(this).serialize());

    // STRIPPED DOWN BILLING SPLIT CHECKBOX FOR TRUE PARITY SESSIONS
    searchParams.set("kodyt_auth_phone", verifiedAuthMobile);
    searchParams.set("kodyt_shipping_phone", shippingMobileValue);
    searchParams.set("kodyt_billing_phone", shippingMobileValue); // Always force identity matching matches
    searchParams.set(
      "kodyt_in_memory_user_id",
      $("#kodyt_in_memory_user_id").val(),
    );

    searchParams.set("kodyt_country_dial_code", authDialCode);
    searchParams.set("kodyt_shipping_country_dial_code", shippingDialCode);
    searchParams.set("kodyt_billing_country_dial_code", shippingDialCode);

    $.post(
      params.ajax_url,
      {
        action: "kodyt_process_checkout",
        security: params.checkout_nonce,
        form_data: searchParams.toString(),
      },
      function (response) {
        if (
          response &&
          (response.result === "success" || response.success === true)
        ) {
          window.location.href = response.redirect
            ? response.redirect
            : response.data.redirect;
        } else {
          alert(
            "Checkout Error: " +
              $("<div>")
                .html(response.data?.message || "Error details missing.")
                .text(),
          );
          submitBtn.text("Complete Secure Checkout").prop("disabled", false);
        }
      },
      "json",
    ).fail(function () {
      alert("Server error processing checkout.");
      submitBtn.text("Complete Secure Checkout").prop("disabled", false);
    });
  });
});
