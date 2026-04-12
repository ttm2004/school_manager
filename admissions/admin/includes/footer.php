<!-- Footer -->
<footer class="admin-footer">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0">
                    <i class="fas fa-copyright me-1"></i> 
                    <?php echo date('Y'); ?> Hệ thống tuyển sinh. 
                    <span class="d-none d-sm-inline">All rights reserved.</span>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="footer-info">
                    <span class="me-3">
                        <i class="fas fa-code-branch me-1"></i>
                        <span class="d-none d-sm-inline">Phiên bản</span> 
                        <span class="badge bg-light text-primary">2.0.0</span>
                    </span>
                    <span>
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('H:i d/m/Y'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
.admin-footer {
    background: white;
    padding: 15px 20px;
    border-top: 1px solid rgba(0,0,0,0.05);
    margin-top: 30px;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.02);
    font-size: 13px;
    color: #6c757d;
    transition: all 0.3s ease;
}

.admin-footer:hover {
    border-top-color: #667eea;
}

.admin-footer p {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 5px;
}

.admin-footer i {
    color: #667eea;
    font-size: 12px;
}

.footer-info {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 15px;
}

.footer-info span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.admin-footer .badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 11px;
}

/* Animation nhẹ */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.admin-footer {
    animation: slideInUp 0.5s ease;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-footer {
        padding: 15px;
        text-align: center;
    }
    
    .admin-footer .row > div {
        text-align: center !important;
    }
    
    .footer-info {
        justify-content: center;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .admin-footer p {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .footer-info {
        flex-direction: column;
        gap: 5px;
    }
    
    .admin-footer {
        font-size: 12px;
    }
}
</style>