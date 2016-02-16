Feature: "Login" links on the Domains List page should not be alwys shown
  In order to see the "Login" link for a particular domains as any user some conditions should be met

  Definitions:
   - "protected domain" - a domain for which a call to Spampanel API - /api/domain/exists/domain/example.com/ - returns "{"present":1}"
   - "unprotected domain" - a domain for which a call to Spampanel API - /api/domain/exists/domain/example.com/ - returns "{"present":0}"

  Scenario: Displaying the "Login" link for an unprotected domain with the "Add the domain to the spamfilter during login if it does not exist" option is disabled
    Given there is an unprotected domain unprotected.test
    And the "Add the domain to the spamfilter during login if it does not exist" option is disabled
    When I open the Domains List page
    Then I should not see the Login link for the unprotected.test domain
    And trying to use a direct link should result in error like "Domain 'unprotected.test' is not protected"

  Scenario: Displaying the "Login" link for an unprotected domain with the "Add the domain to the spamfilter during login if it does not exist" option is enabled
    Given there is an unprotected domain unprotected.test
    And the "Add the domain to the spamfilter during login if it does not exist" option is enabled
    When I open the Domains List page
    Then I should see the Login link for the unprotected.test domain

  Scenario: Displaying the "Login" link for a protected domain
    Given there is a protected domain protected.test
    When I open the Domains List page
    Then I should see the Login link for the protected.test domain
