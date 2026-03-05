# 🎖️ GLOBAL FACTION RADAR

### Military Command & Tactical Management System [Roleplay]

<p align="center">
  <a href="README.es.md"><b>🇪🇸 Ver versión en Español</b></a> | 
  <a href="README.md"><b>🇺🇸 English Version</b></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Status-Operational-green?style=for-the-badge&logo=opsgenie" />
  <img src="https://img.shields.io/badge/Environment-InfinityFree-blue?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Dev-Tarquitet-orange?style=for-the-badge" />
</p>

A comprehensive management system for resources, inventories, and territorial borders designed for **War Thunder Soshy's Server**. Built with a tactical aesthetic inspired by 90s classic command consoles.

<div align="center">
  <table border="0">
    <tr>
      <td><img src="images/README/1772743523519.avif" width="100%" alt="Dashboard Preview" /></td>
      <td><img src="images/README/1772743532007.avif" width="100%" alt="Store Preview" /></td>
    </tr>
    <tr>
      <td><img src="images/README/1772743548358.avif" width="100%" alt="Inventory Preview" /></td>
      <td><img src="images/README/1772743567261.avif" width="100%" alt="Radar Preview" /></td>
    </tr>
  </table>
</div>

## 🛰️ Live Deployment

The system is currently operational at:
🔗 **[SOHYS MILITAR RP - Operation Server](https://sohysmilitarrp.infinityfree.me/index.php)**

## 💬 Community & Roleplay

**War Thunder Soshy's Server**
A War Thunder server featuring a fictional war between nations with Battle Rating (BR) increases over time. Join us to share and have fun in a world of fictional conflict with unique nations and lore shaped by its own players.
🎮 **[Join our Discord](https://discord.gg/xXMjukyw)**

---

## 🚀 System Architecture

### 🗄️ Core Configuration

- [**Database Connection**](config/conexion.php): Secure PDO link to MySQL.
- [**Tactical Dictionary**](config/textos.php): Centralized string management.
- [**Economic Config**](config/precios.php): Global asset cost arrays.

### 🕹️ Operational Modules

- [**Staff Command**](views/staff_dashboard.php): Global resource and jurisdiction management.
- [**Store & Tech Tree**](views/lider_tienda.php): Integrated acquisition system with Fog of War.
- [**Operational Hangar**](views/lider_inventario.php): Stock management and Fleet deployment.
- [**Intelligence Radar**](index.php): Public faction status monitoring.

---

_Developed by [Tarquitet](https://tarquitet.com) - 2026_
