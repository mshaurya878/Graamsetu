-- GraamSetu Realistic Demo Seed Data

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Roles
INSERT INTO roles (id, name, display_name, description) VALUES
(1, 'admin', 'Administrator', 'Village Panchayati & Platform System Admin'),
(2, 'citizen', 'Rural Citizen', 'Village Resident & Consumer of Services'),
(3, 'merchant', 'Local Merchant', 'Shopkeeper, Kirana Store, Medical or CSC Business Owner'),
(4, 'provider', 'Service Provider', 'Skilled Technician, Driver, Electrician, Plumber, Farmer Helper');

-- 2. Categories
INSERT INTO categories (id, name, slug, type, icon_class, description) VALUES
(1, 'Kirana & Grocery', 'kirana-grocery', 'merchant', 'fa-solid fa-basket-shopping', 'Daily essentials, food grains, spices & oil'),
(2, 'Medical & Pharmacy', 'medical-pharmacy', 'merchant', 'fa-solid fa-prescription-bottle-medical', 'Medicines, first aid, healthcare supplies'),
(3, 'CSC & Digital Services', 'csc-digital', 'merchant', 'fa-solid fa-laptop-code', 'Aadhaar update, PAN, certificate filing & printing'),
(4, 'Agri Inputs & Machinery', 'agri-inputs', 'merchant', 'fa-solid fa-wheat-awn', 'Seeds, fertilizers, pesticides & farm equipment'),
(5, 'Electrical & Solar Repair', 'electrical-solar', 'provider', 'fa-solid fa-bolt', 'Home wiring, motor pump repair, solar installation'),
(6, 'Plumbing & Water Supply', 'plumbing-water', 'provider', 'fa-solid fa-faucet-drip', 'Pipe laying, borewell connection, tap fitting'),
(7, 'Vehicle & Tractor Mechanic', 'vehicle-mechanic', 'provider', 'fa-solid fa-wrench', 'Two-wheeler, auto & tractor servicing'),
(8, 'Farm & Transport Logistics', 'farm-transport', 'provider', 'fa-solid fa-truck-pickup', 'Tractor tilling, crop haulage, local mini-truck');

-- 3. Demo Users (Password for all: password123)
-- Hash: $2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq
INSERT INTO users (id, role_id, full_name, email, phone, password_hash, village_name, district, state, pincode, status) VALUES
(1, 1, 'Sarpanch Rajesh Sharma', 'admin@graamsetu.in', '9876543210', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(2, 2, 'Ramesh Kumar Patel', 'ramesh@example.com', '9826011223', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(3, 2, 'Sunita Devi Verma', 'sunita@example.com', '9826022334', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(4, 3, 'Gupta Kirana & General Store', 'gupta.kirana@example.com', '9826033445', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(5, 3, 'Kisan Medical & Wellness', 'kisan.medical@example.com', '9826044556', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(6, 3, 'Gramin Jan Seva Kendra (CSC)', 'janseva.csc@example.com', '9826055667', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(7, 4, 'Suresh Electricals & Motor Works', 'suresh.electric@example.com', '9826066778', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active'),
(8, 4, 'Vikas Tractor Haulage & Tilling', 'vikas.tractor@example.com', '9826077889', '$2y$10$wlPwIDmjdXFvMjKeM3R.Y.U1NqNO.uFhZurZ3p.30.GDdOBuC.tDq', 'Badi Mungawali', 'Sehore', 'Madhya Pradesh', '466001', 'active');

-- 4. Citizen Profiles
INSERT INTO citizen_profiles (user_id, ration_card_type, aadhaar_last4, emergency_contact) VALUES
(2, 'BPL', '4589', '9826099000'),
(3, 'APL', '1243', '9826088111');

-- 5. Merchant Profiles
INSERT INTO merchant_profiles (id, user_id, business_name, category_id, address, latitude, longitude, opening_hours, contact_phone, is_verified, verification_status, description) VALUES
(1, 4, 'Gupta Kirana & General Store', 1, 'Near Panchayat Bhavan, Main Market Road, Badi Mungawali', 23.2054, 77.0872, '07:00 AM - 09:00 PM', '9826033445', 1, 'verified', 'Fresh groceries, edible oils, spices, dairy products and daily household items.'),
(2, 5, 'Kisan Medical & Wellness', 2, 'Opposite Primary Health Center, Badi Mungawali', 23.2062, 77.0890, '08:00 AM - 10:00 PM', '9826044556', 1, 'verified', 'All essential medicines, veterinary products, BP checkup and emergency first aid supplies.'),
(3, 6, 'Gramin Jan Seva Kendra (CSC)', 3, 'Shop No. 4, Bus Stand Complex, Badi Mungawali', 23.2040, 77.0845, '09:00 AM - 07:00 PM', '9826055667', 1, 'verified', 'Official Digital India CSC portal services: Khasra-Khatoni, Caste/Income Certificates, Aadhaar & Ayushman Bharat cards.');

-- 6. Service Providers
INSERT INTO service_providers (id, user_id, service_title, category_id, experience_years, hourly_rate, available_status, is_verified, verification_status, rating_avg, bio) VALUES
(1, 7, 'Certified Electrician & Submersible Repair', 5, 8, 250.00, 'available', 1, 'verified', 4.9, 'Experienced in domestic wiring, motor starter fixing, transformer line checks and solar inverter setup.'),
(2, 8, 'Mahindra Tractor Tilling & Crop Transport', 8, 6, 600.00, 'available', 1, 'verified', 4.8, 'Modern rotavator, seed drill, and heavy trolley transport available for all nearby farm lands.');

-- 7. Products
INSERT INTO products (merchant_id, name, description, price, unit, in_stock) VALUES
(1, 'Fortune Soyabean Oil 1L', 'Refined soyabean cooking oil pouch', 135.00, '1 Liter Pouch', 1),
(1, 'Chakki Fresh Wheat Flour (Atta) 10kg', 'Pure MP Sharbati wheat flour', 340.00, '10 kg Bag', 1),
(1, 'Tata Salt 1kg', 'Iodized crystal table salt', 28.00, '1 kg Packet', 1),
(2, 'Paracetamol Tablets 500mg', 'Fever & body pain relief strip of 10 tablets', 25.00, 'Strip of 10', 1),
(2, 'ORS Rehydration Solution', 'WHO formula orange flavor ORS sachet', 18.00, 'Single Sachet', 1),
(3, 'Khasra-Khatoni Print & Verification', 'Official revenue record copy printout', 50.00, 'Per Document', 1),
(3, 'Ayushman Bharat Golden Card Apply', 'Government health card processing', 30.00, 'Per Card', 1);

-- 8. Provider Services
INSERT INTO services (provider_id, service_name, description, base_price) VALUES
(1, 'Single-Phase Home Wiring Repair', 'Fixing trip issues, short circuit and socket replacement', 250.00),
(1, 'Submersible Motor Starter Overhaul', 'Starter coil change and capacitor replacement', 450.00),
(2, 'Field Rotavator Tilling (Per Acre)', 'Deep soil cultivation using 45HP tractor', 900.00),
(2, 'Crop Haulage Trolley (Per Trip)', 'Transporting harvested grain to Krishi Upaj Mandi', 1200.00);

-- 9. Service Requests
INSERT INTO service_requests (citizen_id, provider_id, service_id, request_details, preferred_date, status, provider_notes) VALUES
(2, 1, 1, 'Main distribution board circuit breaker tripping continuously since morning.', '2026-09-03', 'accepted', 'Will visit tomorrow around 10:00 AM.'),
(3, 2, 3, 'Need 3 acres field rotavating for upcoming Rabi crop sowing.', '2026-09-05', 'pending', NULL);

-- 10. Merchant Enquiries
INSERT INTO merchant_enquiries (citizen_id, merchant_id, subject, message, status, reply_message) VALUES
(2, 1, 'Availability of Urea Fertilizer', 'Do you have 45kg Urea bags in stock right now?', 'replied', 'Yes Ramesh ji, fresh stock arrived today. Price is Rs 266 per bag.');

-- 11. Complaints (Grievance Redressal)
INSERT INTO complaints (citizen_id, category, title, description, village_location, status, resolution_notes) VALUES
(2, 'water', 'Borewell Pipeline Leakage in Ward 4', 'Main drinking water pipeline ruptured near Temple square causing water loss.', 'Ward No. 4, Badi Mungawali', 'under_review', 'Panchayat maintenance crew dispatched for pipe replacement.'),
(3, 'electricity', 'Low Voltage & Frequent Trips', 'Low voltage issues damaging refrigerator and flour mill motors.', 'East Mohalla', 'open', NULL);

-- 12. Announcements
INSERT INTO announcements (admin_id, title, category, content, target_village, is_urgent) VALUES
(1, 'PM Kisan Samman Nidhi 17th Installment KYC Mandatory', 'scheme', 'All registered farmers must complete e-KYC at Gramin Jan Seva Kendra before Sept 15 to receive next installment.', 'All Villages', 1),
(1, 'Free Mega Cattle Health & Vaccination Camp', 'health_camp', 'Government Veterinary Department organizing free foot-and-mouth vaccination for cattle this Saturday at Primary School Ground.', 'Badi Mungawali', 0),
(1, 'Heavy Rainfall & Thunderstorm Advisory', 'weather_alert', 'IMD predicts heavy rain in Sehore district for next 48 hours. Farmers advised to secure harvested grain.', 'All Villages', 1);

-- 13. Notifications
INSERT INTO notifications (user_id, title, message, link_url, is_read) VALUES
(2, 'Service Request Accepted', 'Suresh Electrician accepted your home wiring repair request for 3rd Sept.', 'citizen/requests.php', 0),
(2, 'New Response from Gupta Kirana', 'Gupta Kirana replied to your enquiry regarding Urea fertilizer stock.', 'citizen/dashboard.php', 1),
(3, 'New Village Notice', 'Heavy Rainfall Warning issued by Gram Panchayat.', 'citizen/dashboard.php', 0);

-- 14. Reviews
INSERT INTO reviews (citizen_id, target_type, target_id, rating, comment) VALUES
(2, 'provider', 1, 5, 'Quick response! Fixed our motor pump capacitor within 30 minutes.'),
(3, 'merchant', 1, 5, 'Always has fresh grain and fair prices. Highly recommended.');

-- 15. Admin Logs
INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES
(1, 'System Initialized', 'GraamSetu initial database seed loaded with demo data.', '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;
