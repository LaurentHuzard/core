Feature: Linked Data Platform response headers
  In order to interact with LDP compatible clients
  As an API user
  I need to discover allowed methods and accepted POST formats

  @createSchema
  Scenario: Allow and Accept-Post headers on a collection endpoint
    Given I add "Content-Type" header equal to "application/ld+json"
    When I send a "GET" request to "/ldp_dummies"
    Then the header "Allow" should be equal to "GET, POST"
    And the header "Accept-Post" should be equal to "application/ld+json, application/json, text/turtle"

  @createSchema
  Scenario: Allow header on an item endpoint without Accept-Post
    Given I add "Content-Type" header equal to "application/ld+json"
    And I send a "POST" request to "/ldp_dummies" with body:
    """
    {"name": "Hello"}
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I send a "GET" request to "/ldp_dummies/1"
    Then the header "Allow" should be equal to "DELETE, GET, PATCH, PUT"
    And the header "Accept-Post" should not exist
