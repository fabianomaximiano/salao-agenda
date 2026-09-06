    </div>

</div>

<script
    src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
></script>

<script src="assets/js/app.js"></script>

<?php if (!empty($pageJs)): ?>

    <script
        src="assets/js/<?= htmlspecialchars(
            $pageJs,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    ></script>

<?php endif; ?>

</body>
</html>