/**
 * Global Kodyt Checkout Popup Trigger & Interceptor
 * Runs site-wide to catch clicks before native page redirects or React cancels bubble sequences.
 */
(function () {
  // Use Event Capturing (true) to intercept the event on its way down the DOM tree
  window.addEventListener(
    "click",
    function (e) {
      // Detect standard checkout buttons or WooCommerce Blocks mini-cart checkout buttons
      const checkoutBtn = e.target.closest(
        ".checkout-button, .wc-forward[href*='checkout'], [data-action='kodyt-trigger-popup-checkout'], .wc-block-mini-cart__footer-checkout, [data-block-name='woocommerce/mini-cart-checkout-button-block'], .wc-block-cart__submit-button",
      );

      if (checkoutBtn) {
        // Halt native page jumping or block redirections instantly
        e.preventDefault();
        e.stopPropagation();

        // 1. Target the global layout canvas hidden in the footer
        const popupWrapper = document.getElementById(
          "kodyt-global-popup-checkout-wrapper",
        );

        if (popupWrapper) {
          popupWrapper.style.display = "block";
          document.body.style.overflow = "hidden"; // Trap viewport scroll behind the panel
        }

        // 2. Refresh active cart fragments safely if jQuery is loaded on the page
        if (typeof jQuery !== "undefined") {
          jQuery(document).trigger("wc_fragment_refresh");

          // Auto-trigger first address select processing rules for pre-authenticated profiles
          if (typeof parseAndSyncExistingAddressesToDrawer === "function") {
            parseAndSyncExistingAddressesToDrawer();
          }
        }
      }
    },
    true,
  ); // Crucial: forces capturing phase routing execution

  // Global handler to catch closing dismiss hooks cleanly site-wide
  document.addEventListener("click", function (e) {
    if (
      e.target.closest(".kodyt-popup-modal-dismiss-backdrop-hitbox") ||
      e.target.closest(".kodyt-popup-drag-dismiss-handle")
    ) {
      e.preventDefault();
      const popupWrapper = document.getElementById(
        "kodyt-global-popup-checkout-wrapper",
      );
      if (popupWrapper) {
        popupWrapper.style.display = "none";
        document.body.style.overflow = "auto"; // Re-enable background scrolling
      }
    }
  });

  // =========================================================================
  // AUTOMATIC POPUP RESUMPTION LISTENER
  // =========================================================================
  document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get("kodyt_switch") === "1") {
      // 1. Instantly clean up the browser address bar so it looks premium and professional
      const cleanUrl =
        window.location.protocol +
        "//" +
        window.location.host +
        window.location.pathname;
      window.history.replaceState({ path: cleanUrl }, "", cleanUrl);

      // 2. Fetch the hidden layout canvas wrapper inside the footer markup
      const popupWrapper = document.getElementById(
        "kodyt-global-popup-checkout-wrapper",
      );
      if (popupWrapper) {
        popupWrapper.style.display = "block";
        document.body.style.overflow = "hidden"; // Trap desktop background scrolling

        if (typeof jQuery !== "undefined") {
          jQuery(document).trigger("wc_fragment_refresh");

          // Also run an immediate priming loop
          if (typeof window.initializePopupAddressSelection === "function") {
            window.initializePopupAddressSelection();
          }
        }
      }
    }
  });
})();
