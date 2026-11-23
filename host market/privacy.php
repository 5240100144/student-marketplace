<?php
$pageTitle = "Privacy & Safety Policy";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 0 auto; background: #ffffff; padding: 2.5rem; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">Privacy & Safety Notice</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem;">Last updated: November 2026 &bull; Student Marketplace Guidelines</p>

        <div style="display: flex; flex-direction: column; gap: 1.5rem; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7;">
            <section>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">1. Purpose of the Platform</h2>
                <p>
                    The Student Marketplace is an intra-campus listing service that allows approved student sellers to advertise new and second-hand items (textbooks, electronics, clothing, and hostel necessities) to fellow campus members. The website does not process electronic payments or handle logistics; buyers and sellers communicate directly and arrange physical campus exchanges.
                </p>
            </section>

            <section>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">2. Information We Collect</h2>
                <ul style="padding-left: 1.5rem; margin-top: 0.5rem;">
                    <li><strong>Seller Information:</strong> Full name, verified Student ID, institutional school email, phone number, and a hashed password.</li>
                    <li><strong>Seller Activation Codes:</strong> Single-use activation codes distributed by the project team to authorize registration.</li>
                    <li><strong>Product Listings:</strong> Title, category, price (GH₵), condition, descriptions, and uploaded photographs.</li>
                    <li><strong>Buyer Interest Submissions:</strong> Buyer's name and contact number/email when expressing interest in an item.</li>
                </ul>
            </section>

            <section>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">3. Privacy and Data Safeguards</h2>
                <p>
                    We strictly safeguard personal data:
                </p>
                <ul style="padding-left: 1.5rem; margin-top: 0.5rem;">
                    <li><strong>No Public Exposure:</strong> Student IDs and official university email addresses are strictly private and never displayed on public listing pages.</li>
                    <li><strong>Buyer Details Protected:</strong> Buyer contact details submitted through the interest form are only communicated directly to the respective listing's seller and are never made public.</li>
                    <li><strong>Password Security:</strong> Passwords are cryptographically hashed using standard PHP secure hashing algorithms (bcrypt/argon2).</li>
                </ul>
            </section>

            <section>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">4. Safe Campus Trading Tips</h2>
                <ul style="padding-left: 1.5rem; margin-top: 0.5rem;">
                    <li>Always arrange exchanges in busy, well-lit public campus locations (such as library foyers, campus cafeterias, or student unions).</li>
                    <li>Inspect the item thoroughly before handing over cash or completing mobile money transfers.</li>
                    <li>Never send money in advance before verifying the product in person.</li>
                </ul>
            </section>
        </div>

        <div style="margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center;">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">Return to Marketplace</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

