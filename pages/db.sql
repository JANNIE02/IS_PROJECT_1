-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'donor', 'recipient', 'rider')),
    location VARCHAR(150),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Food listings table
CREATE TABLE food_listings (
    id SERIAL PRIMARY KEY,
    donor_id INTEGER REFERENCES users(id),
    food_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    expiry_date DATE,
    location VARCHAR(150),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'available' CHECK (status IN ('available', 'claimed', 'collected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Claims table
CREATE TABLE claims (
    id SERIAL PRIMARY KEY,
    listing_id INTEGER REFERENCES food_listings(id),
    recipient_id INTEGER REFERENCES users(id),
    pickup_date DATE,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'collected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Deliveries table
CREATE TABLE deliveries (
    id SERIAL PRIMARY KEY,
    claim_id INTEGER REFERENCES claims(id),
    rider_id INTEGER REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'assigned' CHECK (status IN ('assigned', 'picked_up', 'delivered')),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP
);

ALTER TABLE users 
ADD COLUMN latitude DECIMAL(10, 8),
ADD COLUMN longitude DECIMAL(11, 8);




ALTER TABLE food_listings
    ADD COLUMN food_condition VARCHAR(50),
    ADD COLUMN urgency VARCHAR(10) DEFAULT 'medium' CHECK (urgency IN ('high', 'medium', 'low')),
    ADD COLUMN pickup_window VARCHAR(50);


    ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(150);
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255);



CREATE TABLE feedback (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150),
    role VARCHAR(20) NOT NULL DEFAULT 'visitor',
    email VARCHAR(150),
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE user_extra_roles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL CHECK (role IN ('donor','recipient','rider')),
    granted_by INTEGER REFERENCES users(id),  -- which admin granted it
    granted_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE(user_id, role)
);

ALTER TABLE users ADD COLUMN verification_doc TEXT;