# 🎖️ RADAR DE FACCIONES GLOBAL

### Sistema de Mando Militar y Gestión Táctica [Roleplay]

<p align="center">
  <a href="README.md"><b>🇺🇸 View English Version</b></a> | 
  <a href="README.es.md"><b>🇪🇸 Versión en Español</b></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Estado-Operativo-green?style=for-the-badge&logo=opsgenie" />
  <img src="https://img.shields.io/badge/Hosting-InfinityFree-blue?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Autor-Tarquitet-orange?style=for-the-badge" />
</p>

Sistema integral de gestión de recursos, inventarios y fronteras territoriales diseñado para **War Thunder Soshy's Server**. Estética táctica inspirada en las consolas de mando clásicas de los 90.

<div align="center">
  <table border="0">
    <tr>
      <td><img src="images/README/1772743523519.avif" width="100%" alt="Vista Dashboard" /></td>
      <td><img src="images/README/1772743532007.avif" width="100%" alt="Vista Tienda" /></td>
    </tr>
    <tr>
      <td><img src="images/README/1772743548358.avif" width="100%" alt="Vista Inventario" /></td>
      <td><img src="images/README/1772743567261.avif" width="100%" alt="Vista Radar" /></td>
    </tr>
  </table>
</div>

## 🛰️ Despliegue Oficial

El sistema se encuentra operativo en:
🔗 **[SOHYS MILITAR RP - Servidor de Operaciones](https://sohysmilitarrp.infinityfree.me/index.php)**

## 💬 Comunidad y Lore

**War Thunder Soshy's Server**
Servidor de War Thunder de guerra ficticia entre naciones con aumento de BR con el paso del tiempo. Ven a compartir y divertirte en un mundo en conflicto ficticio, con naciones únicas y lore formado por sus propios jugadores.
🎮 **[Únete a nuestro Discord](https://discord.gg/xXMjukyw)**

---

## 🚀 Arquitectura del Sistema

### 🗄️ Configuración Base

- [**Enlace de Datos**](config/conexion.php): Conexión segura PDO a MySQL.
- [**Diccionario Táctico**](config/textos.php): Control centralizado de textos y alertas.
- [**Configuración Económica**](config/precios.php): Definición de costos por rango y clase.

### 🕹️ Módulos Operativos

- [**Mando Staff**](views/staff_dashboard.php): Administración global de recursos y naciones.
- [**Tienda Militar**](views/lider_tienda.php): Sistema de compras con Niebla de Guerra.
- [**Hangar de Inventario**](views/lider_inventario.php): Control de existencias y despliegue de Flotas.
- [**Radar Público**](index.php): Monitor de estado de facciones en tiempo real.

---

_Desarrollado por [Tarquitet](https://tarquitet.com) - 2026_
