# ALX Report API Plugin for Moodle

A secure REST API plugin for IOMAD Moodle that provides multi-tenant course progress and completion data for Power BI integration.

## Features

- **Multi-tenant Security**: Each company can only access their own data
- **Token-based Authentication**: Secure API access with unique tokens per company
- **Course Progress Data**: Comprehensive completion and progress information
- **Power BI Integration**: Optimized for Power BI data refresh
- **Audit Logging**: Complete API access logging
- **Pagination Support**: Handle large datasets efficiently

## Installation

1. **Download the Plugin**
   - Extract the plugin files to your Moodle installation
   - Place in: `[moodle_root]/local/alx_report_api/`

2. **Install via Moodle Admin**
   - Log in as admin
   - Navigate to Site Administration → Notifications
   - Follow the installation prompts

3. **Enable Web Services**
   - Go to Site Administration → Advanced Features
   - Enable "Enable web services"
   - Go to Site Administration → Server → Web Services → Overview
   - Follow the setup wizard

4. **Enable REST Protocol**
   - Go to Site Administration → Server → Web Services → Manage Protocols
   - Enable "REST protocol"

## Configuration

### Step 1: Create API Users

For each company, create a dedicated API user:

1. **Create User Account**
   ```
   Username: [company]_api_user (e.g., alx_api_user)
   Email: api@[company].com
   First Name: [Company] API
   Last Name: User
   ```

2. **Assign to Company**
   - Ensure the user is assigned to the correct company in IOMAD
   - Assign appropriate role (e.g., "Reporter" or custom API role)

### Step 2: Generate API Tokens

1. **Create Token**
   - Go to Site Administration → Server → Web Services → Manage Tokens
   - Click "Create Token"
   - Select the API user
   - Select "alx_report_api" 
   - Set IP restrictions (optional but recommended)
   - Save the token securely

2. **Token Management**
   - Each company should have a unique token
   - Store tokens securely
   - Implement token rotation as needed

## API Usage

### Endpoint

```
POST/GET: [moodle_url]/webservice/rest/server.php
```

### Parameters

- `wstoken`: Your API token
- `wsfunction`: `local_alx_report_api_get_course_progress`
- `moodlewsrestformat`: `json`
- `limit`: Number of records to return (optional, default: 100, max: 1000)
- `offset`: Pagination offset (optional, default: 0)

### Example Request

```bash
curl -X GET "https://your-moodle.com/webservice/rest/server.php" \
  -d "wstoken=your_token_here" \
  -d "wsfunction=local_alx_report_api_get_course_progress" \
  -d "moodlewsrestformat=json" \
  -d "limit=500" \
  -d "offset=0"
```

### Response Format

```json
[
  {
    "userid": 123,
    "firstname": "John",
    "lastname": "Doe",
    "email": "john.doe@company.com",
    "courseid": 456,
    "coursename": "Compliance Training 2024",
    "timecompleted": 1705123456,
    "timestarted": 1705023456,
    "percentage": 100.0,
    "status": "completed"
  }
]
```

### Status Values

- `completed`: Course is fully completed
- `in_progress`: Course has been started but not completed
- `not_started`: Course has not been started

## Power BI Integration

### Basic Setup

1. **Open Power BI Desktop**
2. **Get Data → Web → Advanced**
3. **Enter URL Parts**:
   - URL: `https://your-moodle.com/webservice/rest/server.php`
   - HTTP request body: `wstoken=your_token&wsfunction=local_alx_report_api_get_course_progress&moodlewsrestformat=json`

4. **Configure Data Refresh**
   - Set appropriate refresh intervals
   - Configure incremental refresh for large datasets

### Power Query M Code Example

```m
let
    Source = Web.Contents("https://your-moodle.com/webservice/rest/server.php", [
        Content = Text.ToBinary("wstoken=your_token_here&wsfunction=local_alx_report_api_get_course_progress&moodlewsrestformat=json&limit=1000"),
        Headers = [#"Content-Type"="application/x-www-form-urlencoded"]
    ]),
    JsonResult = Json.Document(Source),
    ConvertedToTable = Table.FromList(JsonResult, Splitter.SplitByNothing(), null, null, ExtraValues.Error),
    ExpandedRecords = Table.ExpandRecordColumn(ConvertedToTable, "Column1", {"userid", "firstname", "lastname", "email", "courseid", "coursename", "timecompleted", "timestarted", "percentage", "status"})
in
    ExpandedRecords
```

## Security Analysis & Recommendations

### Current Security Issues Analysis

This section documents potential security vulnerabilities and recommendations for our ALX Report API plugin. Each item is explained with its current state, potential risks, and suggested solutions.

#### 🔴 Critical Security Issues

**1. Token Exposure in URLs**
- **What it is**: API tokens can be passed as URL parameters (e.g., `?wstoken=abc123`)
- **Why it's dangerous**: 
  - Tokens appear in web server access logs
  - Tokens stored in browser history
  - Tokens sent in HTTP referrer headers to external sites
  - Easy to accidentally share URLs containing tokens
- **Current state in our plugin**: ✅ We currently allow tokens in URLs (standard Moodle web service behavior)
- **Impact**: HIGH - If token is compromised, attacker gets full API access for that company
- **Recommended solution**: Require Authorization header instead: `Authorization: Bearer token123`
- **Implementation effort**: Medium - requires modifying how clients send requests

**2. Cross-Company Data Leakage**
- **What it is**: Bug could allow one company to see another company's data
- **Why it's dangerous**: Violates multi-tenant security, privacy breaches, compliance issues
- **Current state in our plugin**: ✅ GOOD - We use `company_users` table to ensure isolation
- **Potential vulnerability**: If IOMAD tables get corrupted or user has multiple company associations
- **Impact**: CRITICAL - Complete data breach between companies
- **Current protection**: Single company lookup with fallback to false
- **Recommended enhancement**: Add additional validation layers and logging

**3. SQL Injection Possibilities**
- **What it is**: Malicious SQL code injected through API parameters
- **Why it's dangerous**: Could allow database access, data theft, or system compromise
- **Current state in our plugin**: ✅ EXCELLENT - We use Moodle's parameterized queries
- **Protection level**: All queries use `$DB->get_records_sql()` with named parameters
- **Vulnerable areas**: Dynamic SQL building (but we use `$DB->get_in_or_equal()` which is secure)
- **Impact**: CRITICAL - Full database compromise possible
- **Current protection**: Moodle's DB abstraction layer prevents this

#### 🟡 Medium Priority Security Issues

**4. Resource Exhaustion**
- **What it is**: Complex API queries consuming too much CPU/memory
- **Why it's problematic**: Could slow down or crash the server
- **Current state**: Our SQL queries have multiple JOINs and subqueries
- **Current protection**: ✅ Limited to 1000 records max per request
- **Potential issues**: Large datasets, complex calculations, long-running queries
- **Impact**: MEDIUM - Server performance degradation or downtime
- **Recommended solutions**:
  - Add query timeout limits (30 seconds)
  - Set memory limits (256MB per request)
  - Monitor query execution time
  - Add pagination requirements for large requests

**5. Log Flooding**
- **What it is**: Malicious requests filling up the `local_alx_api_logs` table
- **Why it's problematic**: 
  - Disk space exhaustion
  - Database performance degradation
  - Makes legitimate logs hard to find
- **Current state**: We log every API access (IP, user agent, timestamp)
- **Vulnerability**: No limits on log growth
- **Impact**: MEDIUM - System performance and storage issues
- **Recommended solutions**:
  - Implement automatic log cleanup (delete logs older than 90 days)
  - Limit total log entries (keep latest 10,000 per company)
  - Add log rotation scheduled task

**6. Log Injection**
- **What it is**: Malicious data in user agent strings affecting log integrity
- **Why it's problematic**: 
  - Could inject fake log entries
  - Makes log analysis unreliable
  - Potential for log parsing exploits
- **Current state**: We store raw user agent and IP address without sanitization
- **Vulnerability**: No validation of logged data
- **Impact**: LOW-MEDIUM - Log integrity issues
- **Recommended solutions**:
  - Sanitize user agent strings before logging
  - Limit user agent length (255 characters)
  - Validate IP address format

#### 🟢 Lower Priority Security Issues

**7. Log Access Control**
- **What it is**: Who can view the API access logs?
- **Current state**: Only database-level access (admin must run SQL queries)
- **Issue**: No user-friendly interface to monitor API usage
- **Impact**: LOW - Operational inconvenience
- **Recommended solutions**:
  - Create admin interface for viewing logs
  - Add role-based access to log viewing
  - Create security monitoring dashboard

**8. Token Management**
- **What it is**: How tokens are created, rotated, and revoked
- **Current state**: Standard Moodle token management
- **Issues**: 
  - No automatic token expiration
  - No token rotation policy
  - Tokens stored in plain text in database
- **Impact**: MEDIUM - Long-term security risk
- **Recommended solutions**:
  - Implement token expiration policies
  - Add token rotation capabilities
  - Consider token encryption at rest

### Advanced Security Recommendations

#### 1. OAuth 2.0 Implementation
- **What it is**: Modern authentication standard replacing simple tokens
- **Benefits**:
  - Automatic token expiration
  - Refresh token mechanism
  - Better security standards
  - Industry standard
- **Current**: Simple token authentication
- **Effort**: HIGH - Major architectural change
- **Timeline**: Long-term enhancement

#### 2. API Versioning
- **What it is**: Different API versions for security updates
- **Benefits**:
  - Can deprecate insecure endpoints
  - Gradual security improvements
  - Backward compatibility during transitions
- **Current**: Single API version
- **Example**: `/webservice/rest/server.php?version=v2`
- **Effort**: MEDIUM - URL structure changes
- **Timeline**: Medium-term enhancement

#### 3. Enhanced Input Validation
- **What it is**: Strict validation beyond Moodle's basic checks
- **Benefits**:
  - Prevent edge case attacks
  - Better error messages
  - Business logic validation
- **Current**: Basic Moodle parameter validation
- **Examples**:
  - Validate limit is reasonable (not 999999)
  - Check offset doesn't exceed reasonable bounds
  - Validate business rules (user permissions, company access)
- **Effort**: LOW-MEDIUM - Add validation functions
- **Timeline**: Short-term improvement

#### 4. Response Data Filtering
- **What it is**: Automatically remove sensitive data from responses
- **Benefits**:
  - Prevent accidental PII exposure
  - Consistent data protection
  - Compliance with privacy regulations
- **Current**: Admin field controls (manual configuration)
- **Enhancement**: Automatic PII detection and filtering
- **Examples**:
  - Detect email patterns and mask them
  - Remove sensitive fields based on data classification
  - Apply company-specific privacy rules
- **Effort**: MEDIUM - Pattern recognition and filtering logic
- **Timeline**: Medium-term enhancement

#### 5. Rate Limiting Enhancement
- **What it is**: Limit API requests per time period, not just per request
- **Benefits**:
  - Prevent API abuse
  - Ensure fair usage across companies
  - Detect unusual activity patterns
- **Current**: Per-request limits only (max 1000 records)
- **Enhancement**: Time-based limits (e.g., 100 requests per hour per user)
- **Implementation**: Use Redis or Moodle cache for tracking
- **Effort**: MEDIUM - Caching and tracking logic
- **Timeline**: Short-term improvement

### Security Monitoring & Alerting

#### What to Monitor
1. **High Volume Requests**: >1000 requests per hour per company
2. **Failed Authentication**: >10 failures per IP per hour
3. **New IP Access**: First-time access from new IP addresses
4. **Off-hours Access**: API access outside business hours (8 AM - 6 PM)
5. **Data Volume**: Unusually large response sizes
6. **Cross-company Access**: Users accessing multiple companies
7. **Suspicious Patterns**: Same IP accessing many companies

#### Alert Types
- **Critical**: Potential security breaches requiring immediate attention
- **Warning**: Unusual patterns that should be investigated
- **Info**: Notable events for audit purposes

#### Monitoring Implementation Options
1. **Simple**: Email alerts for critical events
2. **Advanced**: Security dashboard with real-time monitoring
3. **Enterprise**: Integration with SIEM systems

### Compliance Considerations

#### GDPR/Privacy Compliance
- **Current Status**: 
  - ✅ Field-level control allows PII restriction
  - ✅ Audit logging for data access tracking
  - ⚠️ Need log retention policy
  - ⚠️ Need data subject access rights implementation
- **Required Actions**:
  - Define log retention periods
  - Implement data deletion on request
  - Add privacy impact documentation

#### SOC 2 / ISO 27001 Compliance
- **Current Status**:
  - ✅ Access logging and monitoring
  - ✅ Multi-tenant data isolation
  - ⚠️ Need encryption in transit verification (HTTPS required)
  - ⚠️ Need regular security assessments
- **Required Actions**:
  - Document security controls
  - Implement regular security testing
  - Create incident response procedures

### Implementation Roadmap

#### Phase 1: Critical Security (Immediate - 1-2 weeks)
1. **Resource Limits**: Add query timeouts and memory limits
2. **Log Sanitization**: Clean user agent and other logged data
3. **Log Rotation**: Implement automatic log cleanup
4. **Enhanced Validation**: Add business rule validation

#### Phase 2: Important Security (Short-term - 1-2 months)
1. **Admin Interface**: Create log viewing interface
2. **Rate Limiting**: Implement time-based request limits
3. **Security Monitoring**: Add basic alert system
4. **Performance Monitoring**: Track query execution times

#### Phase 3: Advanced Security (Long-term - 3-6 months)
1. **OAuth 2.0**: Replace simple token authentication
2. **API Versioning**: Implement version-based endpoints
3. **Advanced Filtering**: Automatic PII detection
4. **SIEM Integration**: Enterprise security monitoring

### Security Testing Recommendations

#### Regular Testing
1. **Penetration Testing**: Annual third-party security assessment
2. **Code Review**: Security-focused code reviews for changes
3. **Vulnerability Scanning**: Automated security scanning
4. **Load Testing**: Performance testing under high load

#### Testing Tools
- **OWASP ZAP**: Web application security scanner
- **SQLMap**: SQL injection testing
- **Burp Suite**: Web security testing platform
- **Apache JMeter**: Load and performance testing

### Cost-Benefit Analysis

#### High-Impact, Low-Cost (Do First)
- Log sanitization and rotation
- Enhanced input validation
- Resource limits
- Basic monitoring

#### High-Impact, Medium-Cost (Do Second)
- Rate limiting implementation
- Admin interface for logs
- Security alerting system

#### Medium-Impact, High-Cost (Do Later)
- OAuth 2.0 implementation
- Advanced PII filtering
- SIEM integration

### Questions for Decision Making

Before implementing each security measure, consider:

1. **Risk Assessment**: What's the actual risk level for our environment?
2. **Cost vs. Benefit**: Is the implementation cost justified by the security improvement?
3. **User Impact**: Will this change affect how clients use the API?
4. **Maintenance**: Can we maintain this security feature long-term?
5. **Compliance**: Is this required for our compliance requirements?

This analysis provides a roadmap for improving the security of our ALX Report API plugin. Each item can be evaluated and implemented based on your specific security requirements and priorities.

## Troubleshooting

### Common Issues

1. **"Invalid user" Error**
   - Ensure the API user exists and is enabled
   - Check that the token is valid and not expired

2. **"No company association" Error**
   - Verify the API user is properly assigned to a company in IOMAD
   - Check company_users table in the database

3. **Empty Results**
   - Verify the company has courses assigned
   - Check that users are enrolled in courses
   - Ensure courses are visible

4. **Power BI Connection Issues**
   - Test the API URL directly in a browser
   - Check firewall settings
   - Verify Power BI Gateway configuration if using on-premises

### Debug Mode

Enable debugging in Moodle to see detailed error messages:
- Site Administration → Development → Debugging
- Set debug level to "DEVELOPER"

## API Logs

The plugin automatically logs all API access attempts in the `local_alx_api_logs` table:

```sql
SELECT * FROM mdl_local_alx_api_logs 
ORDER BY timecreated DESC 
LIMIT 100;
```

## Support

For issues and questions:
1. Check the troubleshooting section above
2. Review Moodle error logs
3. Verify plugin configuration
4. Test with smaller datasets first

## License

This plugin is released under the GNU GPL v3 license.

## Version History

- v1.0.0: Initial release with core functionality
  - Multi-tenant course progress API
  - Token-based authentication
  - Audit logging
  - Power BI integration support
