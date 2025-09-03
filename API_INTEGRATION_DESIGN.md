# API Integration Design Document
## Multi-Platform Ad Management System

### Table of Contents
1. [Integration Overview](#integration-overview)
2. [Google Ads API Integration](#google-ads-api-integration)
3. [Facebook Marketing API Integration](#facebook-marketing-api-integration)
4. [Data Mapping & Transformation](#data-mapping--transformation)
5. [Error Handling & Resilience](#error-handling--resilience)
6. [Rate Limiting & Quotas](#rate-limiting--quotas)
7. [Authentication & Security](#authentication--security)
8. [Monitoring & Logging](#monitoring--logging)

### Integration Overview

#### Platform Support Matrix
| Feature | Google Ads API | Facebook Marketing API | Status |
|---------|---------------|----------------------|---------|
| Campaign Creation | ✅ | ✅ | Supported |
| Ad Group/Set Management | ✅ | ✅ | Supported |
| Creative Management | ✅ | ✅ | Supported |
| Targeting | ✅ | ✅ | Supported |
| Budgeting | ✅ | ✅ | Supported |
| Bidding Strategies | ✅ | ✅ | Supported |
| Performance Metrics | ✅ | ✅ | Supported |
| Real-time Updates | ✅ | ✅ | Supported |

#### Integration Architecture
```
┌─────────────────────────────────────────────────────────────┐
│                    Platform Abstraction Layer               │
├─────────────────────────────────────────────────────────────┤
│    Google Ads      │   Facebook Marketing   │   Future     │
│    Connector       │      Connector         │  Platforms   │
├─────────────────────────────────────────────────────────────┤
│                    Message Queue System                     │
├─────────────────────────────────────────────────────────────┤
│    Rate Limiter    │   Retry Handler    │   Circuit       │
│                    │                    │   Breaker       │
├─────────────────────────────────────────────────────────────┤
│                    External APIs                            │
│   Google Ads API   │  Facebook Graph    │   [Others]      │
└─────────────────────────────────────────────────────────────┘
```

### Google Ads API Integration

#### Authentication Setup
```typescript
interface GoogleAdsCredentials {
  customerId: string
  developerToken: string
  clientId: string
  clientSecret: string
  refreshToken: string
}

class GoogleAdsConnector {
  private client: GoogleAdsApi
  
  constructor(credentials: GoogleAdsCredentials) {
    this.client = new GoogleAdsApi({
      customer_id: credentials.customerId,
      developer_token: credentials.developerToken,
      client_id: credentials.clientId,
      client_secret: credentials.clientSecret,
      refresh_token: credentials.refreshToken
    })
  }
}
```

#### Campaign Operations
```typescript
interface GoogleAdsCampaignService {
  // Campaign Management
  createCampaign(campaignData: GoogleCampaignData): Promise<Campaign>
  updateCampaign(campaignId: string, updates: Partial<GoogleCampaignData>): Promise<Campaign>
  pauseCampaign(campaignId: string): Promise<void>
  resumeCampaign(campaignId: string): Promise<void>
  
  // Ad Group Management
  createAdGroup(campaignId: string, adGroupData: GoogleAdGroupData): Promise<AdGroup>
  updateAdGroup(adGroupId: string, updates: Partial<GoogleAdGroupData>): Promise<AdGroup>
  
  // Ad Management
  createTextAd(adGroupId: string, adData: GoogleTextAdData): Promise<Ad>
  createResponsiveSearchAd(adGroupId: string, adData: GoogleRSAData): Promise<Ad>
  createDisplayAd(adGroupId: string, adData: GoogleDisplayAdData): Promise<Ad>
  
  // Targeting
  addKeywords(adGroupId: string, keywords: Keyword[]): Promise<void>
  addAudiences(campaignId: string, audiences: Audience[]): Promise<void>
  addDemographics(campaignId: string, demographics: Demographic[]): Promise<void>
  
  // Reporting
  getPerformanceReport(campaignId: string, dateRange: DateRange): Promise<PerformanceMetrics>
  getKeywordReport(adGroupId: string, dateRange: DateRange): Promise<KeywordMetrics[]>
}
```

#### Google Ads Data Models
```typescript
interface GoogleCampaignData {
  name: string
  advertisingChannelType: 'SEARCH' | 'DISPLAY' | 'VIDEO' | 'SHOPPING'
  status: 'ENABLED' | 'PAUSED' | 'REMOVED'
  biddingStrategy: {
    type: 'TARGET_CPA' | 'TARGET_ROAS' | 'MAXIMIZE_CLICKS' | 'MANUAL_CPC'
    targetCpa?: number
    targetRoas?: number
  }
  budget: {
    amountMicros: number
    deliveryMethod: 'STANDARD' | 'ACCELERATED'
  }
  startDate: string
  endDate?: string
  geoTargeting: GeoTarget[]
  languageTargeting: string[]
}

interface GoogleAdGroupData {
  name: string
  status: 'ENABLED' | 'PAUSED' | 'REMOVED'
  type: 'SEARCH_STANDARD' | 'DISPLAY_STANDARD'
  cpcBidMicros?: number
  targetingSettings: TargetingSetting[]
}
```

### Facebook Marketing API Integration

#### Authentication Setup
```typescript
interface FacebookCredentials {
  appId: string
  appSecret: string
  accessToken: string
  accountId: string
}

class FacebookConnector {
  private api: FacebookAdsApi
  
  constructor(credentials: FacebookCredentials) {
    this.api = new FacebookAdsApi(credentials.accessToken)
    this.api.setDebug(process.env.NODE_ENV === 'development')
  }
}
```

#### Campaign Operations
```typescript
interface FacebookCampaignService {
  // Campaign Management
  createCampaign(campaignData: FacebookCampaignData): Promise<Campaign>
  updateCampaign(campaignId: string, updates: Partial<FacebookCampaignData>): Promise<Campaign>
  
  // Ad Set Management
  createAdSet(campaignId: string, adSetData: FacebookAdSetData): Promise<AdSet>
  updateAdSet(adSetId: string, updates: Partial<FacebookAdSetData>): Promise<AdSet>
  
  // Creative Management
  createCreative(creativeData: FacebookCreativeData): Promise<Creative>
  uploadImage(imageFile: Buffer, metadata: ImageMetadata): Promise<AdImage>
  uploadVideo(videoFile: Buffer, metadata: VideoMetadata): Promise<AdVideo>
  
  // Ad Management
  createAd(adSetId: string, creativeId: string, adData: FacebookAdData): Promise<Ad>
  
  // Targeting
  createCustomAudience(audienceData: CustomAudienceData): Promise<CustomAudience>
  createLookalikeAudience(sourceAudienceId: string, config: LookalikeConfig): Promise<LookalikeAudience>
  
  // Reporting
  getInsights(adAccountId: string, params: InsightsParams): Promise<AdInsight[]>
  getAdSetInsights(adSetId: string, params: InsightsParams): Promise<AdInsight[]>
}
```

#### Facebook Data Models
```typescript
interface FacebookCampaignData {
  name: string
  objective: 'LINK_CLICKS' | 'CONVERSIONS' | 'REACH' | 'BRAND_AWARENESS' | 'VIDEO_VIEWS'
  status: 'ACTIVE' | 'PAUSED' | 'DELETED'
  specialAdCategories?: ('CREDIT' | 'EMPLOYMENT' | 'HOUSING')[]
  spendCap?: number
  buyingType: 'AUCTION' | 'RESERVED'
}

interface FacebookAdSetData {
  name: string
  campaignId: string
  status: 'ACTIVE' | 'PAUSED' | 'DELETED'
  billingEvent: 'IMPRESSIONS' | 'CLICKS' | 'ACTIONS'
  optimizationGoal: 'REACH' | 'LINK_CLICKS' | 'CONVERSIONS' | 'IMPRESSIONS'
  dailyBudget?: number
  lifetimeBudget?: number
  bidAmount?: number
  startTime: string
  endTime?: string
  targeting: FacebookTargeting
  promotedObject?: PromotedObject
}

interface FacebookTargeting {
  geoLocations: {
    countries?: string[]
    cities?: City[]
    regions?: Region[]
  }
  ageMin?: number
  ageMax?: number
  genders?: (1 | 2)[] // 1: male, 2: female
  interests?: Interest[]
  behaviors?: Behavior[]
  customAudiences?: string[]
  connections?: string[]
  excludedConnections?: string[]
}
```

### Data Mapping & Transformation

#### Unified Ad Format
```typescript
interface UnifiedAdData {
  // Basic Information
  name: string
  description: string
  
  // Creative Elements
  headlines: string[]
  descriptions: string[]
  callToAction: string
  images: AssetReference[]
  videos: AssetReference[]
  
  // Targeting
  targeting: UnifiedTargeting
  
  // Budget & Bidding
  budget: UnifiedBudget
  bidding: UnifiedBidding
  
  // Schedule
  schedule: UnifiedSchedule
  
  // Tracking
  trackingUrls: TrackingUrl[]
}

interface UnifiedTargeting {
  demographics: {
    ageRange: { min: number, max: number }
    genders: ('male' | 'female' | 'unknown')[]
  }
  geography: {
    countries: string[]
    cities: string[]
    regions: string[]
  }
  interests: string[]
  keywords: string[]
  customAudiences: string[]
}
```

#### Platform Mapping Logic
```typescript
class AdDataMapper {
  // Google Ads Mapping
  static toGoogleAds(unifiedData: UnifiedAdData): GoogleCampaignData {
    return {
      name: unifiedData.name,
      advertisingChannelType: this.mapAdvertisingChannel(unifiedData),
      status: 'ENABLED',
      biddingStrategy: this.mapBiddingStrategy(unifiedData.bidding),
      budget: this.mapBudget(unifiedData.budget),
      geoTargeting: this.mapGeoTargeting(unifiedData.targeting.geography),
      languageTargeting: ['en']
    }
  }
  
  // Facebook Mapping
  static toFacebookAds(unifiedData: UnifiedAdData): FacebookCampaignData {
    return {
      name: unifiedData.name,
      objective: this.mapObjective(unifiedData),
      status: 'ACTIVE',
      buyingType: 'AUCTION'
    }
  }
  
  // Reverse Mapping for Reporting
  static fromGoogleAds(googleData: GooglePerformanceData): UnifiedMetrics {
    return {
      impressions: googleData.metrics.impressions,
      clicks: googleData.metrics.clicks,
      spend: googleData.metrics.costMicros / 1_000_000,
      conversions: googleData.metrics.conversions
    }
  }
  
  static fromFacebookAds(facebookData: FacebookInsights): UnifiedMetrics {
    return {
      impressions: parseInt(facebookData.impressions),
      clicks: parseInt(facebookData.clicks),
      spend: parseFloat(facebookData.spend),
      conversions: parseInt(facebookData.actions?.[0]?.value || '0')
    }
  }
}
```

### Error Handling & Resilience

#### Error Categories
```typescript
enum ErrorCategory {
  AUTHENTICATION = 'auth',
  RATE_LIMIT = 'rate_limit',
  QUOTA_EXCEEDED = 'quota',
  VALIDATION = 'validation',
  NETWORK = 'network',
  PLATFORM_ERROR = 'platform',
  TIMEOUT = 'timeout'
}

interface ApiError {
  category: ErrorCategory
  platform: 'google' | 'facebook'
  code: string
  message: string
  retryable: boolean
  retryAfter?: number
  originalError: any
}
```

#### Retry Strategy
```typescript
class RetryHandler {
  private readonly maxRetries = 3
  private readonly baseDelay = 1000 // 1 second
  
  async executeWithRetry<T>(
    operation: () => Promise<T>,
    errorHandler?: (error: ApiError) => boolean
  ): Promise<T> {
    let lastError: ApiError
    
    for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
      try {
        return await operation()
      } catch (error) {
        lastError = this.categorizeError(error)
        
        if (!lastError.retryable || attempt === this.maxRetries) {
          throw lastError
        }
        
        const delay = this.calculateDelay(attempt, lastError)
        await this.sleep(delay)
      }
    }
    
    throw lastError!
  }
  
  private calculateDelay(attempt: number, error: ApiError): number {
    if (error.retryAfter) {
      return error.retryAfter * 1000
    }
    
    // Exponential backoff with jitter
    const exponentialDelay = this.baseDelay * Math.pow(2, attempt)
    const jitter = Math.random() * 0.1 * exponentialDelay
    return exponentialDelay + jitter
  }
}
```

#### Circuit Breaker Pattern
```typescript
class CircuitBreaker {
  private failures = 0
  private lastFailureTime = 0
  private state: 'CLOSED' | 'OPEN' | 'HALF_OPEN' = 'CLOSED'
  
  constructor(
    private threshold = 5,
    private timeout = 60000 // 1 minute
  ) {}
  
  async execute<T>(operation: () => Promise<T>): Promise<T> {
    if (this.state === 'OPEN') {
      if (Date.now() - this.lastFailureTime < this.timeout) {
        throw new Error('Circuit breaker is OPEN')
      }
      this.state = 'HALF_OPEN'
    }
    
    try {
      const result = await operation()
      this.onSuccess()
      return result
    } catch (error) {
      this.onFailure()
      throw error
    }
  }
  
  private onSuccess() {
    this.failures = 0
    this.state = 'CLOSED'
  }
  
  private onFailure() {
    this.failures++
    this.lastFailureTime = Date.now()
    
    if (this.failures >= this.threshold) {
      this.state = 'OPEN'
    }
  }
}
```

### Rate Limiting & Quotas

#### Rate Limit Configuration
```typescript
interface RateLimitConfig {
  platform: 'google' | 'facebook'
  endpoint: string
  requestsPerSecond: number
  requestsPerMinute: number
  requestsPerHour: number
  requestsPerDay: number
  burstLimit: number
}

const RATE_LIMITS: RateLimitConfig[] = [
  {
    platform: 'google',
    endpoint: '/campaign',
    requestsPerSecond: 10,
    requestsPerMinute: 100,
    requestsPerHour: 1000,
    requestsPerDay: 10000,
    burstLimit: 20
  },
  {
    platform: 'facebook',
    endpoint: '/campaigns',
    requestsPerSecond: 25,
    requestsPerMinute: 200,
    requestsPerHour: 4800,
    requestsPerDay: 100000,
    burstLimit: 50
  }
]
```

#### Rate Limiter Implementation
```typescript
class RateLimiter {
  private tokenBuckets = new Map<string, TokenBucket>()
  
  async checkRateLimit(platform: string, endpoint: string): Promise<void> {
    const key = `${platform}:${endpoint}`
    const config = this.getRateLimitConfig(platform, endpoint)
    
    if (!this.tokenBuckets.has(key)) {
      this.tokenBuckets.set(key, new TokenBucket(config))
    }
    
    const bucket = this.tokenBuckets.get(key)!
    
    if (!bucket.tryConsume()) {
      const waitTime = bucket.getWaitTime()
      throw new RateLimitError(`Rate limit exceeded. Retry after ${waitTime}ms`, waitTime)
    }
  }
}

class TokenBucket {
  private tokens: number
  private lastRefill: number
  
  constructor(private config: RateLimitConfig) {
    this.tokens = config.burstLimit
    this.lastRefill = Date.now()
  }
  
  tryConsume(tokens = 1): boolean {
    this.refill()
    
    if (this.tokens >= tokens) {
      this.tokens -= tokens
      return true
    }
    
    return false
  }
  
  private refill() {
    const now = Date.now()
    const timePassed = (now - this.lastRefill) / 1000
    const tokensToAdd = timePassed * this.config.requestsPerSecond
    
    this.tokens = Math.min(this.config.burstLimit, this.tokens + tokensToAdd)
    this.lastRefill = now
  }
}
```

### Authentication & Security

#### OAuth 2.0 Token Management
```typescript
class TokenManager {
  private tokenCache = new Map<string, CachedToken>()
  
  async getAccessToken(userId: string, platform: Platform): Promise<string> {
    const cacheKey = `${userId}:${platform}`
    const cached = this.tokenCache.get(cacheKey)
    
    if (cached && !this.isTokenExpired(cached)) {
      return cached.accessToken
    }
    
    const refreshed = await this.refreshToken(userId, platform)
    this.tokenCache.set(cacheKey, refreshed)
    
    return refreshed.accessToken
  }
  
  private async refreshToken(userId: string, platform: Platform): Promise<CachedToken> {
    const credentials = await this.getStoredCredentials(userId, platform)
    
    switch (platform) {
      case 'google':
        return this.refreshGoogleToken(credentials)
      case 'facebook':
        return this.refreshFacebookToken(credentials)
      default:
        throw new Error(`Unsupported platform: ${platform}`)
    }
  }
}
```

#### API Request Signing
```typescript
class RequestSigner {
  static signGoogleRequest(request: GoogleApiRequest, credentials: GoogleCredentials): void {
    request.headers['Authorization'] = `Bearer ${credentials.accessToken}`
    request.headers['developer-token'] = credentials.developerToken
  }
  
  static signFacebookRequest(request: FacebookApiRequest, credentials: FacebookCredentials): void {
    request.params['access_token'] = credentials.accessToken
    
    // Add app secret proof for enhanced security
    const appSecretProof = crypto
      .createHmac('sha256', credentials.appSecret)
      .update(credentials.accessToken)
      .digest('hex')
    
    request.params['appsecret_proof'] = appSecretProof
  }
}
```

### Monitoring & Logging

#### API Call Monitoring
```typescript
interface ApiCallMetrics {
  platform: Platform
  endpoint: string
  method: string
  statusCode: number
  responseTime: number
  retryCount: number
  userId: string
  timestamp: Date
}

class ApiMonitor {
  async recordApiCall(metrics: ApiCallMetrics): Promise<void> {
    // Send to metrics collection service
    await this.metricsCollector.record('api_call', metrics)
    
    // Log for debugging
    logger.info('API call completed', {
      platform: metrics.platform,
      endpoint: metrics.endpoint,
      statusCode: metrics.statusCode,
      responseTime: metrics.responseTime,
      retryCount: metrics.retryCount
    })
    
    // Alert on errors
    if (metrics.statusCode >= 400) {
      await this.alertService.sendAlert('API_ERROR', metrics)
    }
  }
}
```

#### Error Tracking
```typescript
interface ErrorContext {
  userId: string
  platform: Platform
  operation: string
  requestId: string
  timestamp: Date
  stackTrace: string
  additionalContext: Record<string, any>
}

class ErrorTracker {
  async trackError(error: ApiError, context: ErrorContext): Promise<void> {
    // Send to error tracking service (Sentry, etc.)
    await this.errorService.captureError(error, context)
    
    // Store in database for analysis
    await this.errorRepository.create({
      category: error.category,
      platform: context.platform,
      code: error.code,
      message: error.message,
      userId: context.userId,
      operation: context.operation,
      timestamp: context.timestamp,
      stackTrace: context.stackTrace,
      context: context.additionalContext
    })
  }
}