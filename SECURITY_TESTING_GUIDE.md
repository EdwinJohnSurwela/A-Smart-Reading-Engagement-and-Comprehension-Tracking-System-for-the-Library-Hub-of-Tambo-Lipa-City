# 🔒 Security Testing Guide

## Library Hub Reading System - Tambo, Lipa City

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Automated Security Test Dashboard](#automated-security-test-dashboard)
3. [Manual Security Tests](#manual-security-tests)
4. [Test Credentials](#test-credentials)
5. [Security Features Checklist](#security-features-checklist)
6. [Common Attack Scenarios](#common-attack-scenarios)
7. [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

### Required Software

- ✅ XAMPP (Apache + MySQL + PHP)
- ✅ Modern web browser (Chrome, Firefox, Edge)
- ✅ Text editor (VS Code, Notepad++)
- ✅ Postman or cURL (for API testing)

### Setup Steps

1. Start XAMPP Apache and MySQL
2. Import database: `mysql_database_queries.sql`
3. Navigate to: `http://localhost/FP_SIA_SAD_WST/php/`

---

## 🎯 Automated Security Test Dashboard

### Access the Test Dashboard

1. **Login as Admin**

   ```
   URL: http://localhost/FP_SIA_SAD_WST/php/index.php

   Click: "Admin Dashboard" from Staff Access

   Credentials:
   Email: carmen.lopez@admin.lipacity.edu
   Password: password123
   ```

2. **Navigate to Security Test**

   ```
   URL: http://localhost/FP_SIA_SAD_WST/php/security-test.php

   OR

   From Admin Dashboard → Click "Security Test" link
   ```

3. **What Gets Tested Automatically**

   - ✅ CSRF Token Generation
   - ✅ Password Hashing (Bcrypt)
   - ✅ SQL Injection Protection
   - ✅ XSS Protection
   - ✅ AES-256 Encryption/Decryption
   - ✅ Session Hijacking Protection
   - ✅ Rate Limiting (Brute-Force)
   - ✅ Password Policy Validation
   - ✅ Email Format Validation
   - ✅ Database Security (UTF-8)
   - ✅ Secure Cookie Management

4. **Expected Result**
   ```
   ✅ All 11 tests should PASS (100%)
   ```

---

## 🧪 Manual Security Tests

### Test 1: SQL Injection Protection

**Objective:** Verify the system blocks SQL injection attempts

**Steps:**

1. Go to login page: `http://localhost/FP_SIA_SAD_WST/php/login.php`
2. Try these malicious inputs:

   ```sql
   Username: admin' OR '1'='1
   Password: anything

   Username: '; DROP TABLE users; --
   Password: test

   Username: admin'--
   Password: (leave empty)
   ```

3. **Expected Result:** ❌ Login fails with "Invalid username or password"
4. **What to Check:**
   - No database errors displayed
   - Users table still exists in database
   - System logs the failed attempt

**✅ PASS if:** All malicious inputs are rejected without exposing errors

---

### Test 2: XSS (Cross-Site Scripting) Protection

**Objective:** Prevent malicious JavaScript execution

**Steps:**

1. Register a new student account
2. Enter these in the **Full Name** field:

   ```html
   <script>
     alert("XSS Attack!");
   </script>

   <img src="x" onerror="alert('XSS')" />

   <svg onload=alert('XSS')>
   ```

3. Submit the form
4. View the user's name on dashboard

**Expected Result:** ✅ Scripts are displayed as plain text, NOT executed

**✅ PASS if:** No alert boxes appear, HTML tags are escaped

---

### Test 3: CSRF (Cross-Site Request Forgery) Protection

**Objective:** Prevent unauthorized actions from external sites

**Steps:**

1. Login to the system
2. Open browser DevTools (F12) → Network tab
3. Submit any form (e.g., signup, login)
4. Check the request payload for `csrf_token`
5. Try to submit the form without the token:

   **Using Browser Console:**

   ```javascript
   // Remove CSRF token and resubmit form
   document.querySelector('input[name="csrf_token"]').remove();
   document.querySelector("form").submit();
   ```

**Expected Result:** ❌ Form submission fails with "Invalid security token"

**✅ PASS if:** All forms require valid CSRF tokens

---

### Test 4: Password Security

**Objective:** Enforce strong password policies

**Steps:**

1. Go to signup page
2. Try registering with weak passwords:

   ```
   Password: test       → Should FAIL
   Password: 12345678   → Should FAIL
   Password: password   → Should FAIL
   Password: Test123    → Should FAIL (no special char)
   Password: Test@123   → Should PASS ✅
   ```

3. **Expected Requirements:**
   - Minimum 8 characters
   - At least one uppercase letter
   - At least one lowercase letter
   - At least one number
   - At least one special character

**✅ PASS if:** Weak passwords are rejected with clear error messages

---

### Test 5: Session Hijacking Protection

**Objective:** Prevent session theft

**Steps:**

1. Login to the system
2. Note your IP address
3. **Simulate IP change:**
   - Open DevTools → Application → Cookies
   - Copy the `PHPSESSID` value
   - Open Incognito/Private window
   - Manually set the same `PHPSESSID`
   - Try to access a protected page

**Expected Result:** ❌ Session is destroyed, redirected to login

**Alternative Test (using session_hijack parameter):**

```
http://localhost/FP_SIA_SAD_WST/php/login.php?session_hijack=1
```
