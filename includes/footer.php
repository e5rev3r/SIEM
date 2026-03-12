        </div><!-- /.container-fluid -->
    </div><!-- /.flex-grow-1 -->
</div><!-- /.d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<?php if (isset($extra_js)): ?>
    <?php foreach ((array) $extra_js as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
