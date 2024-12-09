document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', (e) => {
        e.preventDefault();
        const productId = button.dataset.id;

        fetch(`/cart/api/add/${productId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    updateCartCounter(); // Actualise le compteur
                    alert(data.message);
                }
            })
            .catch(error => console.error('Erreur:', error));
    });
});

document.querySelectorAll('.remove-from-cart').forEach(button => {
    button.addEventListener('click', (e) => {
        e.preventDefault();
        const productId = button.dataset.id;

        fetch(`/cart/api/remove/${productId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    updateCartCounter(); // Actualise le compteur
                    alert(data.message);
                }
            })
            .catch(error => console.error('Erreur:', error));
    });
});

function updateCartCounter() {
    fetch('/cart/api/cart/count')
        .then(response => response.json())
        .then(data => {
            const cartCounter = document.getElementById('cart-counter');
            if (cartCounter) {
                cartCounter.textContent = data.count;
            }
        })
        .catch(error => console.error('Erreur lors de la mise à jour du compteur:', error));
}
