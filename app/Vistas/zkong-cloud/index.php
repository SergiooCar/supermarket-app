<?php
/**
 *
 * Vista: ZKONG Cloud (iframe embebido).
 *
 * Muestra la interfaz web de ZKONG Cloud directamente en un iframe apuntando
 * a etiquetas.ausiasmarch.net. Se usa iframe directo en vez del proxy
 * public/zkong_cloud_proxy.php porque el servidor ZKONG permite el embedding
 * desde el dominio de la app (cabecera X-Frame-Options permisiva en producción).
 */
?>
<style>
    #zkong-frame-wrapper {
        margin: -10px -15px -10px -15px;
    }
    #zkong-frame {
        width: 100%;
        height: calc(100vh - 112px);
        border: none;
        display: block;
    }
</style>

<div id="zkong-frame-wrapper">
    <iframe
        id="zkong-frame"
        src="<?= BASE_URL ?>/zkong-cloud/proxy"
        title="ZKONG Cloud"
        allowfullscreen>
    </iframe>
</div>
