document.addEventListener("DOMContentLoaded", () => {
    const ratingInput = document.querySelector('#review_rating');
    const formStars = document.querySelectorAll('#review-rating-form i');
    const confirmButton = document.getElementById("confirmButton");
    const acceptPseudoInput = document.getElementById("accept_pseudo");
    const form = document.querySelector(".form-control.avis form");
    const modalElement = document.getElementById('confirmModal');

    // Fonction pour mettre à jour les étoiles en fonction de la note
    function updateStars(rating) {
        formStars.forEach((star, index) => {
            star.className = index < rating ? 'fas fa-star' : 'far fa-star';
        });
    }

    if (ratingInput && formStars) {
        formStars.forEach(star => {
            star.addEventListener('click', () => {
                const rating = parseInt(star.getAttribute('data-value'), 10);
                ratingInput.value = rating.toString();
                updateStars(rating);
            });
        });

        // Initialisation des étoiles avec la valeur actuelle
        const initialRating = parseInt(ratingInput.value, 10) || 0;
        updateStars(initialRating);
    }

    // Gestion de la soumission après confirmation
    if (confirmButton && form && modalElement) {
        confirmButton.addEventListener("click", () => {
            console.log("Bouton 'J'accepte' cliqué"); // Vérifie que le bouton est cliqué
            if (acceptPseudoInput) {
                acceptPseudoInput.value = "1"; // Marquer l'acceptation du pseudo
                console.log("acceptPseudoInput mis à jour :", acceptPseudoInput.value); // Vérifie la mise à jour
            }

            // Soumettre le formulaire
            if (form) {
                console.log("Formulaire soumis"); // Confirme que le formulaire va être soumis
                form.submit();
            }

            // Fermer le modal
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    console.log("Fermeture du modal"); // Vérifie que le modal va être fermé
                    modal.hide();
                } else {
                    console.log("Modal non trouvé ou déjà fermé"); // En cas de problème avec le modal
                }
            } else {
                console.log("Bootstrap Modal non chargé"); // En cas de problème avec Bootstrap
            }
        });
    }
});
