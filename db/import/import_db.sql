
-- TABLAS
SOURCE ./../tables/0001_crear_tabla_usuarios.sql;
SOURCE ./../tables/0002_crear_tabla_productos.sql;
SOURCE ./../tables/0003_crear_tabla_importaciones.sql;
SOURCE ./../tables/0004_crear_tabla_sesiones.sql;
SOURCE ./../tables/0005_crear_tabla_logs_zkong.sql;
SOURCE ./../tables/0006_crear_tabla_emparejamientos.sql;

-- PROCEDIMIENTOS
SOURCE ./../procedures/0001_sp_actualizar_estado_importacion.sql;
SOURCE ./../procedures/0002_sp_actualizar_estado_zkong.sql;
SOURCE ./../procedures/0003_sp_buscar_usuario_por_email.sql;
SOURCE ./../procedures/0004_sp_cerrar_sesion.sql;
SOURCE ./../procedures/0005_sp_crear_sesion.sql;
SOURCE ./../procedures/0006_sp_crear_usuario.sql;
SOURCE ./../procedures/0007_sp_desemparejar_etiqueta.sql;
SOURCE ./../procedures/0008_sp_editar_usuario.sql;
SOURCE ./../procedures/0009_sp_eliminar_producto.sql;
SOURCE ./../procedures/0010_sp_eliminar_usuario.sql;
SOURCE ./../procedures/0011_sp_guardar_actualizar_producto.sql;
SOURCE ./../procedures/0012_sp_insertar_emparejamiento.sql;
SOURCE ./../procedures/0013_sp_insertar_log_zkong.sql;
SOURCE ./../procedures/0014_sp_listar_usuarios.sql;
SOURCE ./../procedures/0015_sp_login_obtener_usuario.sql;
SOURCE ./../procedures/0016_sp_obtener_emparejamientos.sql;
SOURCE ./../procedures/0017_sp_obtener_producto_por_codigo.sql;
SOURCE ./../procedures/0018_sp_obtener_todos_productos.sql;
SOURCE ./../procedures/0019_sp_registrar_importacion.sql;
SOURCE ./../procedures/0020_sp_toggle_activo_usuario.sql;
SOURCE ./../procedures/0021_sp_validar_sesion.sql;

-- DATA
SOURCE ./../data/0001_rellenar_tabla_usuarios.sql;