# Database Schema Design
## Multi-Platform Ad Management System

### Table of Contents
1. [Schema Overview](#schema-overview)
2. [Core Tables](#core-tables)
3. [Relationships](#relationships)
4. [Indexes](#indexes)
5. [Partitioning Strategy](#partitioning-strategy)
6. [Data Migration](#data-migration)
7. [Backup & Recovery](#backup--recovery)

### Schema Overview

#### Database Technology
- **Primary Database**: PostgreSQL 15+
- **ORM**: Prisma with TypeScript
- **Connection Pooling**: PgBouncer
- **Read Replicas**: 2+ read-only replicas for analytics queries
- **Backup**: Automated daily backups with point-in-time recovery

#### Schema Design Principles
- **Normalization**: 3NF for transactional data, selective denormalization for reporting
- **Audit Trail**: All tables include created_at, updated_at, and soft deletes
- **Multi-tenancy**: User-based isolation with proper indexing
- **Performance**: Optimized indexes for frequent query patterns
- **Scalability**: Partitioned tables for high-volume data (metrics, logs)

### Core Tables

#### 1. User Management

```sql
-- Users table
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role user_role NOT NULL DEFAULT 'user',
    subscription_tier subscription_tier NOT NULL DEFAULT 'free',
    email_verified BOOLEAN DEFAULT false,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- User API credentials (encrypted)
CREATE TABLE user_api_credentials (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    platform platform_type NOT NULL,
    credentials_encrypted TEXT NOT NULL, -- JSON encrypted with app key
    is_active BOOLEAN DEFAULT true,
    last_validated_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(user_id, platform)
);

-- User sessions for JWT token management
CREATE TABLE user_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_jti VARCHAR(255) UNIQUE NOT NULL, -- JWT ID
    refresh_token_hash VARCHAR(255),
    expires_at TIMESTAMPTZ NOT NULL,
    last_used_at TIMESTAMPTZ DEFAULT NOW(),
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### 2. Asset Management

```sql
-- Media assets (images, videos, documents)
CREATE TABLE assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INTEGER NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_url TEXT NOT NULL,
    thumbnail_url TEXT,
    width INTEGER,
    height INTEGER,
    duration INTEGER, -- For videos
    metadata JSONB, -- Additional file metadata
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Platform-specific asset mappings
CREATE TABLE platform_assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    asset_id UUID NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    platform platform_type NOT NULL,
    platform_asset_id VARCHAR(255) NOT NULL,
    platform_url TEXT,
    platform_metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(asset_id, platform)
);
```

#### 3. Ad Management

```sql
-- Core ad definitions
CREATE TABLE ads (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ad_status DEFAULT 'draft',
    unified_data JSONB NOT NULL, -- Unified ad configuration
    targeting_data JSONB NOT NULL, -- Unified targeting configuration
    budget_data JSONB NOT NULL, -- Budget and bidding configuration
    schedule_data JSONB, -- Campaign scheduling
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Ad assets relationship
CREATE TABLE ad_assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ad_id UUID NOT NULL REFERENCES ads(id) ON DELETE CASCADE,
    asset_id UUID NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    asset_type asset_type NOT NULL, -- primary_image, secondary_image, video, etc.
    order_index INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(ad_id, asset_id, asset_type)
);
```

#### 4. Campaign Management

```sql
-- Platform-specific campaigns
CREATE TABLE campaigns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ad_id UUID NOT NULL REFERENCES ads(id) ON DELETE CASCADE,
    platform platform_type NOT NULL,
    platform_campaign_id VARCHAR(255), -- External platform ID
    campaign_name VARCHAR(255) NOT NULL,
    status campaign_status DEFAULT 'pending',
    platform_data JSONB, -- Platform-specific configuration
    error_message TEXT,
    last_sync_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(ad_id, platform)
);

-- Campaign deployment history
CREATE TABLE campaign_deployments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    deployment_type deployment_type NOT NULL,
    status deployment_status NOT NULL,
    started_at TIMESTAMPTZ NOT NULL,
    completed_at TIMESTAMPTZ,
    error_details JSONB,
    platform_response JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### 5. Performance Metrics

```sql
-- Daily aggregated metrics (partitioned by date)
CREATE TABLE campaign_metrics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    impressions BIGINT DEFAULT 0,
    clicks BIGINT DEFAULT 0,
    conversions DECIMAL(10,2) DEFAULT 0,
    spend_amount DECIMAL(15,4) DEFAULT 0, -- In account currency
    spend_currency VARCHAR(3) DEFAULT 'USD',
    cpc DECIMAL(10,4) DEFAULT 0,
    ctr DECIMAL(8,4) DEFAULT 0,
    conversion_rate DECIMAL(8,4) DEFAULT 0,
    roas DECIMAL(10,2) DEFAULT 0,
    platform_metrics JSONB, -- Platform-specific metrics
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(campaign_id, date)
) PARTITION BY RANGE (date);

-- Create monthly partitions for metrics
CREATE TABLE campaign_metrics_2024_01 PARTITION OF campaign_metrics
FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Hourly metrics for real-time monitoring (recent data only)
CREATE TABLE campaign_metrics_hourly (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    hour_timestamp TIMESTAMPTZ NOT NULL,
    impressions BIGINT DEFAULT 0,
    clicks BIGINT DEFAULT 0,
    conversions DECIMAL(10,2) DEFAULT 0,
    spend_amount DECIMAL(15,4) DEFAULT 0,
    platform_metrics JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(campaign_id, hour_timestamp)
) PARTITION BY RANGE (hour_timestamp);
```

#### 6. Reporting & Analytics

```sql
-- Custom reports configuration
CREATE TABLE reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type report_type NOT NULL,
    configuration JSONB NOT NULL, -- Report parameters, filters, etc.
    schedule JSONB, -- Cron schedule for automated reports
    is_scheduled BOOLEAN DEFAULT false,
    last_generated_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Generated report instances
CREATE TABLE report_instances (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    status report_instance_status DEFAULT 'pending',
    file_url TEXT,
    file_size INTEGER,
    generated_at TIMESTAMPTZ,
    error_message TEXT,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### 7. System Tables

```sql
-- API rate limiting tracking
CREATE TABLE api_rate_limits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    platform platform_type NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    requests_count INTEGER DEFAULT 0,
    window_start TIMESTAMPTZ NOT NULL,
    window_end TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(user_id, platform, endpoint, window_start)
);

-- System audit logs
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    entity_type VARCHAR(100) NOT NULL, -- Table name
    entity_id UUID NOT NULL,
    action audit_action NOT NULL,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Background jobs tracking
CREATE TABLE background_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_type VARCHAR(100) NOT NULL,
    job_data JSONB NOT NULL,
    status job_status DEFAULT 'pending',
    priority INTEGER DEFAULT 0,
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    scheduled_at TIMESTAMPTZ DEFAULT NOW(),
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    error_message TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

### Custom Types (Enums)

```sql
-- User roles
CREATE TYPE user_role AS ENUM ('admin', 'user', 'viewer');

-- Subscription tiers
CREATE TYPE subscription_tier AS ENUM ('free', 'starter', 'professional', 'enterprise');

-- Platform types
CREATE TYPE platform_type AS ENUM ('google_ads', 'facebook_ads', 'microsoft_ads', 'tiktok_ads');

-- Ad status
CREATE TYPE ad_status AS ENUM ('draft', 'active', 'paused', 'completed', 'archived');

-- Campaign status
CREATE TYPE campaign_status AS ENUM ('pending', 'active', 'paused', 'error', 'completed');

-- Deployment types
CREATE TYPE deployment_type AS ENUM ('create', 'update', 'pause', 'resume', 'delete');

-- Deployment status
CREATE TYPE deployment_status AS ENUM ('pending', 'in_progress', 'completed', 'failed');

-- Asset types
CREATE TYPE asset_type AS ENUM ('primary_image', 'secondary_image', 'logo', 'video', 'document');

-- Report types
CREATE TYPE report_type AS ENUM ('performance', 'conversion', 'audience', 'custom');

-- Report instance status
CREATE TYPE report_instance_status AS ENUM ('pending', 'generating', 'completed', 'failed');

-- Audit actions
CREATE TYPE audit_action AS ENUM ('create', 'update', 'delete', 'login', 'logout');

-- Job status
CREATE TYPE job_status AS ENUM ('pending', 'running', 'completed', 'failed', 'cancelled');
```

### Indexes

#### Performance Indexes
```sql
-- User indexes
CREATE INDEX idx_users_email ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_role ON users(role) WHERE deleted_at IS NULL;

-- API credentials indexes
CREATE INDEX idx_api_credentials_user_platform ON user_api_credentials(user_id, platform);
CREATE INDEX idx_api_credentials_active ON user_api_credentials(is_active) WHERE is_active = true;

-- Session indexes
CREATE INDEX idx_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_sessions_expires ON user_sessions(expires_at) WHERE expires_at > NOW();

-- Ad indexes
CREATE INDEX idx_ads_user_id ON ads(user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_ads_status ON ads(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_ads_created_at ON ads(created_at DESC) WHERE deleted_at IS NULL;

-- Campaign indexes
CREATE INDEX idx_campaigns_ad_id ON campaigns(ad_id);
CREATE INDEX idx_campaigns_platform ON campaigns(platform);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_campaigns_sync ON campaigns(last_sync_at DESC) WHERE status = 'active';

-- Metrics indexes
CREATE INDEX idx_metrics_campaign_date ON campaign_metrics(campaign_id, date DESC);
CREATE INDEX idx_metrics_date ON campaign_metrics(date DESC);
CREATE INDEX idx_hourly_metrics_campaign_hour ON campaign_metrics_hourly(campaign_id, hour_timestamp DESC);

-- Asset indexes
CREATE INDEX idx_assets_user_id ON assets(user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_platform_assets_asset_platform ON platform_assets(asset_id, platform);

-- Audit log indexes
CREATE INDEX idx_audit_logs_user_entity ON audit_logs(user_id, entity_type, entity_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at DESC);

-- Background jobs indexes
CREATE INDEX idx_jobs_status_priority ON background_jobs(status, priority DESC, scheduled_at);
CREATE INDEX idx_jobs_type_status ON background_jobs(job_type, status);
```

#### Composite Indexes for Complex Queries
```sql
-- User campaigns performance query
CREATE INDEX idx_user_campaigns_performance 
ON campaigns(user_id, status, created_at DESC) 
INCLUDE (platform, platform_campaign_id);

-- Multi-platform ad metrics aggregation
CREATE INDEX idx_metrics_multi_platform 
ON campaign_metrics(date DESC, campaign_id) 
INCLUDE (impressions, clicks, spend_amount);
```

### Partitioning Strategy

#### Metrics Table Partitioning
```sql
-- Function to create monthly partitions automatically
CREATE OR REPLACE FUNCTION create_monthly_partition(table_name TEXT, start_date DATE)
RETURNS TEXT AS $$
DECLARE
    partition_name TEXT;
    end_date DATE;
BEGIN
    partition_name := table_name || '_' || to_char(start_date, 'YYYY_MM');
    end_date := (start_date + INTERVAL '1 month')::DATE;
    
    EXECUTE format('CREATE TABLE IF NOT EXISTS %I PARTITION OF %I 
                   FOR VALUES FROM (%L) TO (%L)',
                   partition_name, table_name, start_date, end_date);
    
    RETURN partition_name;
END;
$$ LANGUAGE plpgsql;

-- Create partitions for the next 24 months
DO $$
DECLARE
    i INTEGER;
    partition_date DATE;
BEGIN
    FOR i IN 0..23 LOOP
        partition_date := (CURRENT_DATE + (i || ' months')::INTERVAL)::DATE;
        partition_date := date_trunc('month', partition_date)::DATE;
        PERFORM create_monthly_partition('campaign_metrics', partition_date);
        PERFORM create_monthly_partition('campaign_metrics_hourly', partition_date);
        PERFORM create_monthly_partition('audit_logs', partition_date);
    END LOOP;
END $$;
```

### Data Migration

#### Migration Scripts Structure
```sql
-- Migration versioning
CREATE TABLE schema_migrations (
    version VARCHAR(50) PRIMARY KEY,
    applied_at TIMESTAMPTZ DEFAULT NOW(),
    rollback_sql TEXT
);

-- Example migration: Add new platform support
-- migrations/001_add_tiktok_platform.sql
BEGIN;

-- Add new platform type
ALTER TYPE platform_type ADD VALUE 'tiktok_ads';

-- Update any constraints or defaults that reference the enum
-- (Platform-specific logic here)

INSERT INTO schema_migrations (version, rollback_sql) VALUES 
('001_add_tiktok_platform', 'BEGIN; /* rollback commands here */ COMMIT;');

COMMIT;
```

### Backup & Recovery

#### Backup Strategy
```sql
-- Daily backup script
pg_dump --format=custom --compress=9 --file=backup_$(date +%Y%m%d).dump ads_platform_db

-- Point-in-time recovery setup
-- Enable WAL archiving in postgresql.conf:
-- wal_level = replica
-- archive_mode = on
-- archive_command = 'cp %p /backup/wal/%f'

-- Recovery script example
pg_basebackup -D /recovery/base -Ft -z -P
# Then restore with:
# pg_ctl -D /recovery/base start
```

#### Data Retention Policy
```sql
-- Cleanup old data (run as scheduled job)
DELETE FROM campaign_metrics_hourly 
WHERE hour_timestamp < NOW() - INTERVAL '7 days';

DELETE FROM user_sessions 
WHERE expires_at < NOW() - INTERVAL '30 days';

DELETE FROM audit_logs 
WHERE created_at < NOW() - INTERVAL '2 years';

DELETE FROM background_jobs 
WHERE status IN ('completed', 'failed') 
AND completed_at < NOW() - INTERVAL '30 days';
```

### Performance Monitoring

#### Key Metrics to Monitor
```sql
-- Query performance monitoring
SELECT query, calls, total_time, mean_time, rows 
FROM pg_stat_statements 
ORDER BY total_time DESC LIMIT 10;

-- Table size monitoring
SELECT schemaname, tablename, 
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables 
WHERE schemaname = 'public' 
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Index usage monitoring
SELECT schemaname, tablename, indexname, idx_scan, idx_tup_read, idx_tup_fetch
FROM pg_stat_user_indexes 
ORDER BY idx_scan DESC;
```

This database schema provides a robust foundation for the multi-platform ad management system with proper normalization, indexing, partitioning, and scalability considerations.