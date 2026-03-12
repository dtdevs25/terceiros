    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.</p>
            <p>Desenvolvido com ❤️ para gestão eficiente de funcionários</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- JavaScript adicional da página -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- JavaScript inline da página -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?php echo $inline_js; ?>
        </script>
    <?php endif; ?>
    
    <style>
    .footer {
        background: var(--white);
        border-top: 1px solid var(--light-gray);
        padding: 20px 30px;
        margin-top: auto;
        margin-left: var(--sidebar-width);
        transition: var(--transition-medium);
    }
    
    .sidebar.collapsed ~ .content .footer {
        margin-left: var(--sidebar-collapsed-width);
    }
    
    .footer-content {
        text-align: center;
        color: var(--gray);
        font-size: 0.9rem;
    }
    
    .footer-content p {
        margin: 5px 0;
    }
    
    @media (max-width: 767px) {
        .footer {
            margin-left: 0;
            padding: 15px;
        }
        
        .footer-content {
            font-size: 0.8rem;
        }
    }
    </style>
</body>
</html>

