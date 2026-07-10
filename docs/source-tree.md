PS D:\CV\FREELANCE\vin-eyewear> tree /f
Folder PATH listing for volume Tệp
Volume serial number is 000000D7 D26B:6125
D:.
│   .env.example
│   .gitignore
│   .htaccess
│   index.php
│   README.md
│
├───app
│   ├───controllers
│   │   │   AboutController.php
│   │   │   ApiController.php
│   │   │   ArController.php
│   │   │   AuthController.php
│   │   │   BookingController.php
│   │   │   ContactController.php
│   │   │   ErrorController.php
│   │   │   EventController.php
│   │   │   HomeController.php
│   │   │   OrderController.php
│   │   │   ProductController.php
│   │   │
│   │   └───Admin
│   │           CustomerAdminController.php
│   │           DashboardController.php
│   │           InventoryAdminController.php
│   │           OrderAdminController.php
│   │           ProductAdminController.php
│   │
│   ├───middleware
│   │       AuthMiddleware.php
│   │       GuestMiddleware.php
│   │
│   ├───models
│   │       AdminModel.php
│   │       BookingModel.php
│   │       CategoryModel.php
│   │       ContactModel.php
│   │       CustomerModel.php
│   │       EventModel.php
│   │       InventoryModel.php
│   │       OrderModel.php
│   │       ProductModel.php
│   │       UserModel.php
│   │
│   ├───services
│   │       EmailService.php
│   │       ValidationService.php
│   │
│   └───views
│       ├───about
│       │       index.php
│       │       policies.php
│       │       story.php
│       │       warranty.php
│       │
│       ├───admin
│       │   ├───auth
│       │   │       login.php
│       │   │
│       │   ├───customers
│       │   │       index.php
│       │   │
│       │   ├───dashboard
│       │   │       index.php
│       │   │
│       │   ├───inventory
│       │   │       index.php
│       │   │
│       │   ├───orders
│       │   │       detail.php
│       │   │       index.php
│       │   │
│       │   ├───products
│       │   │       create.php
│       │   │       edit.php
│       │   │       index.php
│       │   │
│       │   └───_layout
│       │           footer.php
│       │           header.php
│       │           sidebar.php
│       │
│       ├───ar
│       │       comparison.php
│       │       facial_measurement.php
│       │       tryon.php
│       │
│       ├───auth
│       │       login.php
│       │       profile.php
│       │       register.php
│       │
│       ├───booking
│       │       feedback.php
│       │       glasses_measurement.php
│       │       index.php
│       │       partner.php
│       │
│       ├───contact
│       │       index.php
│       │
│       ├───event
│       │       index.php
│       │
│       ├───home
│       │       banner.php
│       │       best_seller.php
│       │       index.php
│       │
│       ├───order
│       │       checkout.php
│       │       fail.php
│       │       payment.php
│       │       success.php
│       │
│       ├───product
│       │       detail.php
│       │       index.php
│       │
│       └───_layout
│               breadcrumb.php
│               footer.php
│               header.php
│               master.php
│
├───assets
│   ├───css
│   │       about.css
│   │       about.story.css
│   │       ar.css
│   │       ar.facial.css
│   │       ar.tryon.css
│   │       booking.css
│   │       booking.pd.css
│   │       contact.css
│   │       event.css
│   │       global.css
│   │       home.css
│   │       layout.css
│   │       order.css
│   │       product.css
│   │       product.detail.css
│   │
│   ├───fonts
│   │       .gitkeep
│   │
│   ├───icons
│   │       .gitkeep
│   │
│   ├───images
│   │       .gitkeep
│   │
│   ├───js
│   │       ai-processor.js
│   │       ar-engine.js
│   │       ar.facial.js
│   │       ar.tryon.js
│   │       booking.pd.js
│   │       contact.js
│   │       global.js
│   │       home.js
│   │       order.js
│   │       product.detail.js
│   │       product.js
│   │
│   ├───models
│   │       .gitkeep
│   │
│   └───uploads
│           .gitkeep
│
├───config
│       app.php
│       database.php
│       routes.php
│
├───core
│       App.php
│       BaseController.php
│       BaseModel.php
│       Database.php
│       helpers.php
│       Router.php
│
├───database
│   │   schema.sql
│   │
│   └───migrations
│           .gitkeep
│
├───docs
│       brain.md
│       nhiem-vu.md
│       server-info.md
│       sitemap.png
│       [Output] Kính mắt.md
│
└───errors
        404.php
        500.php
