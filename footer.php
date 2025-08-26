    </main>
    <footer>
        <div class="container">
            <p>Ticket Reservation System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));
    </script>
</body>
</html>