# IS_PROJECT_1

# 🥗 Food Redistribution & Hunger Relief Management System

> A web-based platform connecting food donors with humanitarian organisations to reduce waste and fight hunger.

**👨‍💻 Jannie Birungi Mudondo** & **👩‍💻 Mosoti Sherly Kemunto**  
🏫 Strathmore University — BBIT Programme | 👩‍🏫 Supervisor: Ms. Mkabane

---

## 🌍 Overview

This system bridges the gap between food donors — farmers, market traders, and retailers — and verified recipient organisations like NGOs and community shelters. Instead of edible surplus ending up in landfills, this platform makes sure it reaches people who need it most through real-time digital coordination.

---

## ✨ Features

- 🌾 **Donor registration** — farms, markets, and retail donors can list surplus food
- 🏠 **Recipient verification** — NGOs and shelters get verified and can browse listings
- 🤝 **Automated matching** — connects donors to nearby recipients based on proximity and capacity
- 🚴 **Volunteer logistics** — rider dispatch and delivery coordination
- 🔔 **Real-time notifications** — SMS, email, and in-app alerts
- 🛡️ **Admin dashboard** — platform oversight, user management, compliance monitoring
- 📊 **Impact reporting** — track kg of food rescued and meals provided

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| 🎨 Frontend | HTML, CSS, Bootstrap, JavaScript |
| ⚙️ Backend | PHP |
| 🗄️ Database | Postgres |
| 🔧 Version Control | Git / GitHub |

---

## 📁 Project Structure

```
food-redistribution-system/
├── 📂 assets/
│   ├── 📂 css/
│   │   ├── main.css
│   │   └── dashboard.css
│   ├── 📂 js/
│   │   ├── main.js
│   │   ├── matching.js
│   │   └── notifications.js
│   └── 📂 img/
├── 📂 pages/
│   ├── index.html               ← 🏠 Landing page
│   ├── login.html               ← 🔐 Login
│   ├── register-donor.html      ← 🌾 Donor signup
│   ├── register-recipient.html  ← 🏠 Recipient signup
│   ├── donor-dashboard.html     ← 📦 Donor portal
│   ├── donor-listing.html       ← ➕ Add food listing
│   ├── recipient-dashboard.html ← 🍽️ Recipient portal
│   ├── recipient-browse.html    ← 🔍 Browse available food
│   ├── volunteer-dashboard.html ← 🚴 Rider portal
│   ├── admin-dashboard.html     ← 🛡️ Admin overview
│   ├── admin-users.html         ← 👥 User management
│   └── impact-report.html       ← 📊 Impact dashboard
├── 📂 components/
│   ├── navbar.html
│   ├── sidebar.html
│   ├── food-card.html
│   └── alert-toast.html
├── 📂 data/
│   ├── db.sql                   ← 🗄️ Database schema
│   └── seed.sql                 ← 🌱 Sample data
├── README.md
└── .gitignore
```

---

## 👥 Partner Roles

### 👨‍💻 Jannie Birungi Mudondo
- 🔐 Authentication system (login, registration, sessions)
- 🌾 Donor portal (register, dashboard, food listings)
- 🛡️ Admin dashboard and user management
- ⚙️ Backend matching logic (`matching.js`)
- 🗄️ Database schema and seed data (`db.sql`, `seed.sql`)

### 👩‍💻 Mosoti Sherly Kemunto
- 🏠 Recipient portal (register, dashboard, browse)
- 🚴 Volunteer rider interface
- 📊 Impact reporting dashboard
- 🎨 UI/UX styling and Bootstrap layout
- 🔔 Notifications UI (`notifications.js`)
- 🧩 Reusable components (food-card, alert-toast)

### 🤝 Shared Responsibilities
- 🏠 Landing page (`index.html`)
- 🧭 Navigation and sidebar
- 🔄 Code review and integration testing
- 📝 Documentation

---

## 🚀 Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-repo-url.git
   ```
2. **Set up the database** — import `data/db.sql` into MySQL
3. **Configure backend** — update your PHP database connection settings
4. **Run locally** — use XAMPP or WAMP, open `pages/index.html`
5. **You're live! 🎉**

---

## 👤 System Actors

| Actor | Role |
|---|---|
| 🌾 Farm donor | Lists surplus agricultural produce |
| 🏪 Market trader | Lists unsold end-of-day stock |
| 🛒 Retail donor | Lists near-expiry packaged goods |
| 🏠 NGO / Shelter | Browses and claims available food |
| 🚴 Volunteer rider | Collects and delivers food |
| 🛡️ System admin | Manages users, verifies accounts, monitors platform |

---

## 🎓 Academic Context

Developed as part of the Information Systems project requirement for the **Bachelor of Business Information Technology (BBIT)** programme at **Strathmore University**, Nairobi, Kenya.

---

> *"Reducing food waste, one donation at a time."* 🌱
