/**
 * Feature Module: Coupon Logic Transformations
 */
jQuery(document).ready(function ($) {
  const params = kodyt_checkout_params;

  $(document).on("click", "#kodyt-toggle-coupon-field", function (e) {
    e.preventDefault();
    $("#kodyt-coupon-slide-container").slideToggle(300);
    let arrow = $(".kodyt-coupon-arrow");
    arrow.text(arrow.text() === "▼" ? "▲" : "▼");
  });

  $(document).on("click", "#kodyt-btn-apply-coupon", function (e) {
    e.preventDefault();
    e.stopPropagation();
    let code = $("#kodyt-coupon-code-input").val().trim();
    let feedback = $("#kodyt-coupon-feedback-msg");
    if (!code) return;
    $(this).prop("disabled", true).text("...");

    $.post(
      params.ajax_url,
      {
        action: "kodyt_apply_checkout_coupon",
        security: params.checkout_nonce,
        coupon_code: code,
      },
      (response) => {
        $(this).prop("disabled", false).text("Apply");
        if (response.success) {
          feedback
            .css("color", "#10b981")
            .text(response.data.message || "Coupon added!")
            .show();
          $("#kodyt-coupon-code-input").val("");
          $("#kodyt-calc-subtotal").html(response.data.subtotal);
          $("#kodyt-calc-grandtotal").html(response.data.grandtotal);
          $("#kodyt-applied-coupons-rows-wrap").html(response.data.rows_html);
        } else {
          feedback
            .css("color", "#ef4444")
            .text(response.data.message || "Invalid coupon.")
            .show();
        }
      },
      "json",
    );
  });

  $(document).on("click", ".kodyt-remove-coupon-link", function (e) {
    e.preventDefault();
    e.stopPropagation();
    let code = $(this).data("coupon");
    let feedback = $("#kodyt-coupon-feedback-msg");

    $.post(
      params.ajax_url,
      {
        action: "kodyt_remove_checkout_coupon",
        security: params.checkout_nonce,
        coupon_code: code,
      },
      function (response) {
        if (response.success) {
          let res = response.data || response;
          $("#kodyt-calc-subtotal").html(res.subtotal);
          $("#kodyt-calc-grandtotal").html(res.grandtotal);
          $("#kodyt-applied-coupons-rows-wrap").html(res.rows_html || "");
          feedback
            .css("color", "#64748b")
            .text("Coupon removed.")
            .show()
            .delay(2000)
            .fadeOut();
        }
      },
      "json",
    );
  });
});
