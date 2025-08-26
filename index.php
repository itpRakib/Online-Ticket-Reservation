<?php include 'db_connect.php'; ?>
<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Reservation System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="index.php">TicketReserve</a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="bookings.php" class="nav-link">My Bookings</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="nav-link">Login</a>
                        </li>
                        <li class="nav-item">
                            <a href="register.php" class="nav-link">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>Book Your Journey With Ease</h1>
                <p>Find and book bus, train, and plane tickets all in one place</p>
                <a href="#search" class="btn-primary">Book Now</a>
            </div>
        </section>

        <section id="search" class="search-section">
            <div class="container">
                <h2>Find Your Trip</h2>
                <form action="search_results.php" method="GET" class="search-form">
                    <div class="form-group">
                        <label for="from">From</label>
                        <input type="text" id="from" name="from" placeholder="Departure city" required>
                    </div>
                    <div class="form-group">
                        <label for="to">To</label>
                        <input type="text" id="to" name="to" placeholder="Arrival city" required>
                    </div>
                    <div class="form-group">
                        <label for="date">Departure Date</label>
                        <input type="date" id="date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="transport-type">Transport Type</label>
                        <select id="transport-type" name="type">
                            <option value="">Any</option>
                            <option value="1">Bus</option>
                            <option value="2">Train</option>
                            <option value="3">Plane</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="passengers">Passengers</label>
                        <input type="number" id="passengers" name="passengers" min="1" max="10" value="1">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>Why Choose Us</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-bus"></i>
                        <h3>Bus Tickets</h3>
                        <p>Book tickets for all major bus routes across the country.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-train"></i>
                        <h3>Train Tickets</h3>
                        <p>Reserve your train seats with just a few clicks.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-plane"></i>
                        <h3>Flight Tickets</h3>
                        <p>Find the best flight deals for your travel needs.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2023 Ticket Reservation System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Set minimum date to today
        document.getElementById('date').min = new Date().toISOString().split("T")[0];
        
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