function filterDropdown(input, containerId) {
    const filterText = input.value.toUpperCase();
    const links = document.querySelectorAll(`#${containerId} a`);
    links.forEach(link => {
        const txtValue = link.textContent || link.innerText;
        link.parentElement.style.display =
            txtValue.toUpperCase().includes(filterText) ? "" : "none";
    });
}
