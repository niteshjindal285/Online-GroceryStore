# 🚀 Quick Database Import Guide

## ✅ All Issues Fixed!

The sample data file has been corrected to match the database schema perfectly. All column names and MySQL syntax are now compatible.

---

## 📝 What Was Fixed

### 1. **Column Name Mismatches**
- ✅ `companies.company_type` → `companies.type`
- ✅ `users.active` → `users.is_active`
- ✅ Added missing `company_id` to locations

### 2. **MySQL Compatibility**
- ✅ Replaced `NOW()` with `CURRENT_TIMESTAMP`
- ✅ Fixed date intervals to use `DATE_SUB()` and `DATE_ADD()`
- ✅ Added proper `USE mjr_group_erp;` statement

### 3. **Foreign Key Dependencies**
- ✅ Added `units_of_measure` data before inventory items
- ✅ Ordered inserts correctly to respect foreign key constraints

---

## 🔥 Import Commands (Copy & Paste)

### Step 1: Create Database
```sql
CREATE DATABASE mjr_group_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 2: Import Schema
```bash
mysql -u root -p mjr_group_erp < php_erp/config/database_schema.sql
```

**OR using phpMyAdmin:**
1. Select `mjr_group_erp` database
2. Click **Import** tab
3. Choose `php_erp/config/database_schema.sql`
4. Click **Go**

### Step 3: Import Sample Data
```bash
mysql -u root -p mjr_group_erp < php_erp/config/sample_data.sql
```

**OR using phpMyAdmin:**
1. Select `mjr_group_erp` database
2. Click **Import** tab
3. Choose `php_erp/config/sample_data.sql`
4. Click **Go**

---

## 🎯 Verify Import Success

After importing, run these SQL commands to verify:

```sql
USE mjr_group_erp;

-- Check companies (should show 3)
SELECT * FROM companies;

-- Check users (should show 3)
SELECT username, email, role FROM users;

-- Check inventory items (should show 3)
SELECT code, name FROM inventory_items;

-- Check sales orders (should show 3)
SELECT order_number, status FROM sales_orders;
```

**Expected Results:**
- ✅ 3 companies
- ✅ 3 users (admin, john.manager, jane.user)
- ✅ 3 inventory items
- ✅ 3 sales orders

---

## 🔐 Login Credentials

| Username | Password | Role |
|----------|----------|------|
| admin | password123 | admin |
| john.manager | password123 | manager |
| jane.user | password123 | user |

---

## ⚡ Quick Test

After import:

1. **Start PHP Server:**
   ```bash
   cd php_erp/public
   php -S localhost:5000
   ```

2. **Open Browser:**
   ```
   http://localhost:5000
   ```

3. **Login:**
   - Username: `admin`
   - Password: `password123`

4. **Verify Data:**
   - Dashboard should show metrics
   - Inventory → Items should list 3 items
   - Finance → Chart of Accounts should show 15 accounts
   - Sales → Orders should show 3 orders

---

## 🐛 Troubleshooting

### Error: "Unknown column 'active'"
**Status:** ✅ FIXED - Column is now correctly named `is_active`

### Error: "Unknown column 'company_type'"  
**Status:** ✅ FIXED - Column is now correctly named `type`

### Error: "Cannot add or update a child row"
**Status:** ✅ FIXED - Foreign key dependencies are now in correct order

### Error: "You have an error in your SQL syntax"
**Status:** ✅ FIXED - All PostgreSQL syntax replaced with MySQL

---

## 📊 What's Included in Sample Data

| Entity | Count | Examples |
|--------|-------|----------|
| Companies | 3 | MJR HQ, Manufacturing, Logistics |
| Users | 3 | admin, john.manager, jane.user |
| Categories | 3 | Electronics, Raw Materials, Finished Goods |
| Units of Measure | 3 | Pieces, Kilograms, Meters |
| Locations | 3 | Warehouse, Production Floor, Retail Store |
| Inventory Items | 3 | Widget Pro 3000, Steel Sheets, Circuit Board |
| Customers | 3 | Acme Corp, Global Industries, Tech Solutions |
| Suppliers | 3 | Steel Suppliers, Electronics Wholesale, Mfg Supplies |
| Sales Orders | 3 | SO-2024-001, SO-2024-002, SO-2024-003 |
| Purchase Orders | 3 | PO-2024-001, PO-2024-002, PO-2024-003 |
| Work Orders | 3 | WO-2024-001, WO-2024-002, WO-2024-003 |
| Chart of Accounts | 15 | Assets, Liabilities, Equity, Revenue, Expenses |
| MRP Planned Orders | 3 | Purchase and production orders |

---

## ✅ Success Checklist

- [ ] Database `mjr_group_erp` created
- [ ] Schema imported without errors
- [ ] Sample data imported successfully
- [ ] Can query tables and see data
- [ ] PHP server starts without errors
- [ ] Can access http://localhost:5000
- [ ] Can login with admin/password123
- [ ] Dashboard displays correctly
- [ ] All modules show data

---

## 🎉 All Set!

Your database is now ready with:
- ✅ **100% MySQL Compatible** - No PostgreSQL syntax
- ✅ **Correct Column Names** - All fields match schema
- ✅ **Proper Foreign Keys** - Dependencies in correct order
- ✅ **Sample Data** - 3 entries per module for testing

**No more import errors!** 🚀
