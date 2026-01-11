<div align="center">
  <img src="./logo/logo.svg" width="355px" alt="PHP HTML PARSER">
  <p>Lightweight PHP library for parsing and manipulating HTML documents.</p>
</div>

## 📖 Requirements
* PHP 7.0 or higher
* [Composer](https://getcomposer.org) is required for installation
* PHP Extensions: `iconv`, `ext-mbstring`

## 📦 Installation

Install the library using Composer:

```shell
$ composer require richarddobron/dom-forge
```

## ⚡️ Quick Start

### Creating DomForge Instance

```php
// Without configuration
$dom1 = DomForge::fromHtml('<div class="container"><p>Hello World</p></div>');
$dom2 = DomForge::fromFile('/path/to/file.html');

// With configuration
$config = (new Configuration())
    ->setLowercase(true)
    ->setRemoveLineBreaks(true)
    ->setTargetCharset('UTF-8');

$dom1 = DomForge::fromHtml('<div>Content</div>', $config);
$dom2 = DomForge::fromFile('/path/to/file.html', $config);


```

### Finding Elements

```php
// Find all matching elements
$elements = $dom->find('div.container');

// Find first matching element
$element = $dom->findOne('p');

// Find by index (0-based)
$element = $dom->find('p', 0);

// Find with CSS selectors
$dom->find('#id');           // By ID
$dom->find('.class');        // By class
$dom->find('div p');         // Descendant
$dom->find('div > p');       // Direct child
$dom->find('div + p');       // Adjacent sibling
$dom->find('div, p');        // Multiple selectors
$dom->find('[attribute]');   // Has attribute
$dom->find('[attr=value]');  // Attribute equals
$dom->find('[attr^=val]');   // Attribute starts with
$dom->find('[attr$=val]');   // Attribute ends with
$dom->find('[attr*=val]');   // Attribute contains
```

### Getting Content

```php
$element = $dom->findOne('div');

// Get inner HTML (child elements as HTML string)
$inner = $element->innerHtml();

// Get outer HTML (including the element itself)
$outer = $element->outerHtml();

// Get text content (strips HTML tags)
$text = $element->textContent();

// Using magic properties
$inner = $element->innerHtml;
$outer = $element->outerHtml;
$text = $element->textContent;
```

### Setting Content

```php
$element = $dom->findOne('div');

// Set inner HTML
$element->innerHtml = '<p>New content</p>';

// Set outer HTML (replaces the element)
$element->outerHtml = '<span>Replaced</span>';
```

### Creating Elements

```php
// Create an element
$span = $dom->createElement('span');
$span = $dom->createElement('span', 'Hello World');  // with content
$span = $dom->createElement('a', 'Click', ['href' => 'https://example.com', 'class' => 'btn']);

// Create a text node
$textNode = $dom->createTextNode('Hello World');

// Create a comment
$comment = $dom->createComment('This is a comment');
```

### DOM Manipulation

```php
// Append a child element
$container = $dom->findOne('#container');
$newElement = $dom->createElement('p', 'New paragraph');
$container->appendChild($newElement);

// Remove a child element
$child = $container->firstChild();
$container->removeChild($child);

// Insert before another element
$ul = $dom->findOne('ul');
$newLi = $dom->createElement('li', 'First item');
$existingLi = $ul->firstChild();
$ul->insertBefore($newLi, $existingLi);

// Check if element has children
if ($container->hasChildren()) {
    // ...
}
```

### Working with Attributes

```php
$element = $dom->findOne('a');

// Get attribute
$href = $element->getAttribute('href');

// Check if attribute exists
if ($element->hasAttribute('target')) {
    // ...
}

// Set attribute
$element->setAttribute('class', 'link');

// Get all attributes
$attrs = $element->getAttributes();

// Remove attribute
$element->removeAttribute('class');

// Magic property access
$href = $element->href;
$element->class = 'active';
```

### Node Type Checking

```php
$node = $dom->findOne('div');

$node->isElement();   // true for HTML elements
$node->isText();      // true for text nodes
$node->isComment();   // true for comment nodes
$node->isSelfClosing();  // true for self-closing tags (br, img, etc.)
$node->isNamespacedElement();  // true for elements like fbt:param
```

### Traversing Nodes

```php
$element = $dom->findOne('ul');

// Get parent
$parent = $element->parent;

// Get children
$children = $element->children();         // All child nodes
$firstChild = $element->children(0);      // First child by index
$firstChild = $element->firstChild();     // First child
$lastChild = $element->lastChild();       // Last child

// Get siblings
$next = $element->nextSibling();          // Next sibling element
$prev = $element->previousSibling();      // Previous sibling element

// Get nodes (includes text nodes)
$nodes = $element->nodes;
```

### Lookup Methods

```php
// Get element by ID
$element = $dom->getElementById('myId');

// Get elements by ID (with index support)
$elements = $dom->getElementsById('myId');
$element = $dom->getElementsById('myId', 0);

// Get element by tag name
$element = $dom->getElementByTagName('div');

// Get elements by tag name
$elements = $dom->getElementsByTagName('p');
$element = $dom->getElementsByTagName('p', 0);
```

### Callbacks

```php
$dom = DomForge::fromHtml('<div><p>Test</p></div>');

// Set a callback that runs when getting outerHtml
$dom->setCallback(function ($node) {
    // Process each node
});

// Remove callback
$dom->removeCallback();
```

### Saving Output

```php
// Get HTML as string
$html = $dom->save();

// Save to file
$dom->save('/path/to/file.html');

// Or use __toString
$html = (string) $dom;
```

## ⚙️ Configuration Options

You can customize the library with the following methods:

| Method                                        | Description                                                                      | Default   |
|-----------------------------------------------|----------------------------------------------------------------------------------|-----------|
| `setTargetCharset(string $targetCharset)`     | Sets the target character set for the HTML content.                              | `'UTF-8'` |
| `setLowercase(bool $lowercase)`               | Enables or disables converting tag and attribute names to lowercase.             | `true`    |
| `setForceTagsClosed(bool $forceTagsClosed)`   | Enables or disables forcing all tags to be closed.                               | `true`    |
| `setRemoveLineBreaks(bool $removeLineBreaks)` | Enables or disables stripping of `\r` and `\n` characters from the HTML content. | `false`   |
| `setDefaultBrText(string $defaultBrText)`     | Sets the default text to use for `<br>` tags.                                    | `"\n"`    |
| `setDefaultSpanText(string $defaultSpanText)` | Sets the default text to use for `<span>` tags.                                  | `''`      |
| `setSelfClosingTags(array $selfClosingTags)`  | Sets the extra list of self-closing tags.                                        | `null`    |

## 📅 Change Log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🧪 Testing

```shell
$ composer tests
```

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


## ⚖️ License
This repository is MIT licensed, as found in the [LICENSE](LICENSE) file.
