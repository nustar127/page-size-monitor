document.addEventListener("DOMContentLoaded", function () {
  const trigger = document.getElementById(
    "wp-admin-bar-display_page_info_child_6",
  );
  const modal = document.getElementById("wp-heavy-images-modal");
  const closeBtn = document.querySelector(".wp-hi-modal-close");

  if (trigger && modal) {
    trigger.addEventListener("click", function (e) {
      e.preventDefault();
      modal.style.display = "flex";
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", function () {
      modal.style.display = "none";
    });
  }

  window.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.style.display = "none";
    }
  });
});
