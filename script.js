function returnToEditor() {
  window.history.back();
}

function downloadPdf() {
  window.print();
}

const postFields = document.getElementById("post-fields");
const addPost = document.getElementById("add-post");
const form = document.querySelector("form");
const infoButton = document.getElementById("info-button");
const infoPanel = document.getElementById("info-panel");

if (infoButton && infoPanel) {
  infoButton.addEventListener("click", () => {
    const isOpen = infoButton.getAttribute("aria-expanded") === "true";
    infoButton.setAttribute("aria-expanded", String(!isOpen));
    infoPanel.hidden = isOpen;
  });
}

if (postFields && addPost && form) {
  const formStateKey = "professionalPortfolioFormState";
  const maxPosts = 20;
  let postNumber = postFields.querySelectorAll('input[name="urls[]"]').length;

  function getPostItems() {
    return Array.from(postFields.querySelectorAll("[data-post-item]"));
  }

  function normalizeCarouselLinks() {
    getPostItems().forEach((item) => {
      const input = item.querySelector('input[name="urls[]"]');
      const carouselIndex = item.querySelector(".carousel-index");
      if (!carouselIndex.value || Number(carouselIndex.value) < 1) return;
      const match = input.value.match(
        /^(https?:\/\/(?:www\.)?instagram\.com\/(?:[^/?#]+\/)*p\/[A-Za-z0-9_-]+)\/?(?:\?.*)?$/i,
      );
      if (match)
        input.value = `${match[1]}/?img_index=${Math.floor(Number(carouselIndex.value))}`;
    });
  }

  function addPostField(value = "", carouselIndex = "") {
    if (getPostItems().length >= maxPosts) return;
    postNumber += 1;
    const item = document.createElement("div");
    item.className = "post-item";
    item.dataset.postItem = "";
    item.innerHTML = `
            <div class="post-item-bar">
                <span>Instagram post</span>
            </div>
            <input type="text" name="urls[]" id="post-url-${postNumber}" placeholder="https://www.instagram.com/p/CXXXXXXXXXX/" required>
            <label class="carousel-option">
                Carousel image index
              <span class="carousel-stepper">
                <button type="button" class="carousel-step" data-step="1" aria-label="Increase carousel image index">↑</button>
                <input type="number" name="carousel_index[]" class="carousel-index" min="1" step="1" value="${carouselIndex}">
                <button type="button" class="carousel-step" data-step="-1" aria-label="Decrease carousel image index">↓</button>
              </span>
            </label>`;
    const input = item.querySelector('input[name="urls[]"]');
    input.type = "text";
    input.value = value;
    const removeButton = document.createElement("button");
    removeButton.type = "button";
    removeButton.className = "remove-post";
    removeButton.setAttribute("aria-label", "Remove post");
    removeButton.hidden = true;
    removeButton.textContent = "Remove";
    item.append(removeButton);
    postFields.append(item);
    bindPostItem(item);
    updateRemoveButtons();
    updateAddButton();
  }

  function saveFormState() {
    const state = {
      posts: getPostItems().map((item) => ({
        url: item.querySelector('input[name="urls[]"]').value,
        carouselIndex: item.querySelector(".carousel-index").value,
      })),
      format: form.elements.format.value,
      font: form.elements.font.value,
      placement: form.elements.placement.value,
      cover_title: form.elements.cover_title.value,
      cover_name: form.elements.cover_name.value,
      cover_date: form.elements.cover_date.value,
      show_numbers: form.elements.show_numbers.checked,
      cover_bold: form.elements.cover_bold.checked,
    };
    sessionStorage.setItem(formStateKey, JSON.stringify(state));
  }

  function restoreFormState() {
    const saved = sessionStorage.getItem(formStateKey);
    if (!saved) return;

    try {
      const state = JSON.parse(saved);
      const posts = state.posts || [];
      posts
        .slice(getPostItems().length, maxPosts)
        .forEach((post) => addPostField(post.url, post.carouselIndex));
      getPostItems().forEach((item, index) => {
        const post = posts[index];
        if (!post) return;
        item.querySelector('input[name="urls[]"]').value = post.url || "";
        item.querySelector(".carousel-index").value = post.carouselIndex || "";
      });
      form.elements.format.value = state.format || form.elements.format.value;
      form.elements.font.value = state.font || form.elements.font.value;
      form.elements.placement.value =
        state.placement || form.elements.placement.value;
      form.elements.cover_title.value = state.cover_title || "";
      form.elements.cover_name.value = state.cover_name || "";
      form.elements.cover_date.value = state.cover_date || "";
      form.elements.show_numbers.checked = Boolean(state.show_numbers);
      form.elements.cover_bold.checked = Boolean(state.cover_bold);
    } catch (error) {
      sessionStorage.removeItem(formStateKey);
    }
  }

  function bindPostItem(item) {
    item.querySelector(".carousel-index").addEventListener("change", () => {
      normalizeCarouselLinks();
      saveFormState();
    });
    item.querySelectorAll(".carousel-step").forEach((button) => {
      button.addEventListener("click", () => {
        const input = item.querySelector(".carousel-index");
        const currentValue = Number(input.value) || 1;
        input.value = Math.max(1, currentValue + Number(button.dataset.step));
        input.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });
    item.querySelector(".remove-post").addEventListener("click", () => {
      if (getPostItems().length <= 4) return;
      item.remove();
      updateRemoveButtons();
      updateAddButton();
      saveFormState();
    });
  }

  function updateRemoveButtons() {
    const canRemove = getPostItems().length > 4;
    getPostItems().forEach((item) => {
      const removeButton = item.querySelector(".remove-post");
      removeButton.hidden = !canRemove;
      removeButton.setAttribute("aria-hidden", String(!canRemove));
    });
  }

  function updateAddButton() {
    const atLimit = getPostItems().length >= maxPosts;
    addPost.disabled = atLimit;
    addPost.textContent = atLimit ? "20 posts added" : "Add post";
  }

  form.addEventListener("input", saveFormState);
  form.addEventListener("change", saveFormState);
  form.addEventListener("submit", () => {
    normalizeCarouselLinks();
    saveFormState();
  });

  addPost.addEventListener("click", (event) => {
    event.preventDefault();
    addPostField();
    saveFormState();
  });

  getPostItems().forEach(bindPostItem);
  restoreFormState();
  updateRemoveButtons();
  updateAddButton();
}
