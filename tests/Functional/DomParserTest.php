<?php

declare(strict_types=1);

namespace dobron\DomForge\Tests\Functional;

use dobron\DomForge\Configuration;
use dobron\DomForge\DomForge;
use dobron\DomForge\Node;
use PHPUnit\Framework\TestCase;

class DomParserTest extends TestCase
{
    private static function parse(string $str)
    {
        if (empty($str)) {
            return false;
        }

        $configuration = (new Configuration())
            ->setLowercase(false)
            ->setForceTagsClosed(true)
            ->setTargetCharset('UTF-8')
            ->setRemoveLineBreaks(false)
            ->setDefaultBrText("\n")
            ->setDefaultSpanText(' ');

        return DomForge::fromHtml($str, $configuration);
    }

    public function testConfiguration()
    {
        $configuration = Configuration::create()
            ->setLowercase(false)
            ->setForceTagsClosed(true)
            ->setTargetCharset('UTF-8')
            ->setRemoveLineBreaks(false)
            ->setDefaultBrText("\n")
            ->setDefaultSpanText(' ');

        $this->assertFalse($configuration->isLowercase());
        $this->assertTrue($configuration->isForceTagsClosed());
        $this->assertEquals('UTF-8', $configuration->getTargetCharset());
        $this->assertFalse($configuration->shouldRemoveLineBreaks());
        $this->assertEquals("\n", $configuration->getDefaultBrText());
        $this->assertEquals(' ', $configuration->getDefaultSpanText());
    }

    public function testBasicHtmlParsing()
    {
        $html = '<div class="container"><p>Hello World</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $this->assertNotNull($dom->root);
    }

    public function testFindByTagName()
    {
        $html = '<div><p>First</p><p>Second</p><span>Third</span></div>';
        $dom = $this->parse($html);

        $paragraphs = $dom->find('p');
        $this->assertCount(2, $paragraphs);
    }

    public function testFindById()
    {
        $html = '<div id="main"><p id="content">Hello</p></div>';
        $dom = $this->parse($html);

        $element = $dom->find('#content', 0);
        $this->assertInstanceOf(Node::class, $element);
        $this->assertEquals('content', $element->getAttribute('id'));
    }

    public function testFindByClass()
    {
        $html = '<div class="box"><p class="text highlight">Hello</p><p class="text">World</p></div>';
        $dom = $this->parse($html);

        $textElements = $dom->find('.text');
        $this->assertCount(2, $textElements);

        $highlightElements = $dom->find('.highlight');
        $this->assertCount(1, $highlightElements);
    }

    public function testFindByAttribute()
    {
        $html = '<input type="text" name="email"><input type="password" name="pass">';
        $dom = $this->parse($html);

        $textInput = $dom->find('input[type=text]', 0);
        $this->assertNotNull($textInput);
        $this->assertEquals('email', $textInput->getAttribute('name'));
    }

    public function testSelfClosingTags()
    {
        $html = '<div><br><img src="test.jpg"><input type="text"></div>';
        $dom = $this->parse($html);

        $br = $dom->find('br', 0);
        $this->assertNotNull($br);
        $this->assertTrue($dom->isSelfClosingTag('br'));
        $this->assertTrue($dom->isSelfClosingTag('img'));
        $this->assertTrue($dom->isSelfClosingTag('input'));
    }

    public function testInnerAndOuterHtml()
    {
        $html = '<div class="box"><p>Hello <strong>World</strong></p></div>';
        $dom = $this->parse($html);

        $div = $dom->find('div', 0);
        $this->assertContains('<p>Hello <strong>World</strong></p>', $div->innerHtml());
        $this->assertContains('<div class="box">', $div->outerHtml());
    }

    public function testTextContent()
    {
        $html = '<div><p>Hello</p><p>World</p></div>';
        $dom = $this->parse($html);

        $textContent = $dom->root->textContent();
        $this->assertContains('Hello', $textContent);
        $this->assertContains('World', $textContent);
    }

    public function testNodeTypes()
    {
        $html = '<div>Text content</div>';
        $dom = $this->parse($html);

        $div = $dom->find('div', 0);
        $this->assertTrue($div->isElement());
        $this->assertFalse($div->isComment());
        $this->assertFalse($div->isText());
    }

    public function testAttributeManipulation()
    {
        $html = '<div id="test" class="box" data-value="123"></div>';
        $dom = $this->parse($html);

        $div = $dom->find('div', 0);

        $this->assertEquals('test', $div->getAttribute('id'));
        $this->assertEquals('box', $div->getAttribute('class'));
        $this->assertEquals('123', $div->getAttribute('data-value'));
        $this->assertTrue($div->hasAttribute('id'));
        $this->assertFalse($div->hasAttribute('nonexistent'));

        $allAttrs = $div->getAttributes();
        $this->assertArrayHasKey('id', $allAttrs);
        $this->assertArrayHasKey('class', $allAttrs);
    }

    public function testNestedElements()
    {
        $html = '<div><ul><li>One</li><li>Two</li></ul></div>';
        $dom = $this->parse($html);

        $listItems = $dom->find('li');
        $this->assertCount(2, $listItems);

        $ul = $dom->find('ul', 0);
        $this->assertCount(2, $ul->children());
    }

    public function testChildSelector()
    {
        $html = '<div><p>Direct child</p><span><p>Nested</p></span></div>';
        $dom = $this->parse($html);

        $directChildren = $dom->find('div > p');
        $this->assertCount(1, $directChildren);
    }

    public function testInvalidHtmlIframeWithMetaRefresh()
    {
        $html = '<iframe><meta http-equiv="refresh" content="1;/>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $this->assertNotNull($dom->root);

        // Parser should handle this gracefully
        $iframe = $dom->find('iframe', 0);
        $this->assertNotNull($iframe);
    }

    public function testUnclosedTags()
    {
        $html = '<div><p>Unclosed paragraph<p>Another paragraph</div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $paragraphs = $dom->find('p');
        $this->assertGreaterThanOrEqual(1, count($paragraphs));
    }

    public function testMismatchedTags()
    {
        $html = '<div><span>Content</div></span>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $this->assertNotNull($dom->root);
    }

    public function testMalformedAttributes()
    {
        $html = '<div class=unquoted data-test="mixed\' weird="value>Content</div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $div = $dom->find('div', 0);
        $this->assertNotNull($div);
    }

    public function testMissingClosingBracket()
    {
        $html = '<div class="test"<p>Content</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $this->assertNotNull($dom->root);
    }

    public function testEmptyTags()
    {
        $html = '<div></div><span></span><p></p>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $divs = $dom->find('div');
        $this->assertCount(1, $divs);
    }

    public function testNestedQuotesInAttributes()
    {
        $html = '<div data-json=\'{"key": "value"}\' onclick="alert(\'test\')">Content</div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $div = $dom->find('div', 0);
        $this->assertNotNull($div);
    }

    public function testScriptAndStyleStripping()
    {
        $html = '<div><script>alert("test");</script><style>.test { color: red; }</style><p>Content</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
    }

    public function testHtmlComments()
    {
        $html = '<div><!-- This is a comment --><p>Content</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
    }

    public function testDoctypeHandling()
    {
        $html = '<!DOCTYPE html><html><body><p>Content</p></body></html>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
    }

    public function testCdataSections()
    {
        $html = '<div><![CDATA[Some CDATA content]]><p>Normal content</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
    }

    public function testSpecialCharactersAndEntities()
    {
        $html = '<div>&amp; &lt; &gt; &quot; &#39; &nbsp;</div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $div = $dom->find('div', 0);
        $this->assertNotNull($div);
    }

    public function testUnicodeContent()
    {
        $html = '<div>日本語 中文 한국어 العربية 🎉 🚀 💻</div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $div = $dom->find('div', 0);
        $this->assertNotNull($div);
        $this->assertContains('日本語', $div->innerHtml());
        $this->assertContains('🎉', $div->innerHtml());
    }

    public function testDeeplyNestedHtml()
    {
        $html = '<div><div><div><div><div><p>Deep content</p></div></div></div></div></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
        $this->assertContains('Deep content', $p->innerHtml());
    }

    public function testMultipleRootElements()
    {
        $html = '<div>First</div><div>Second</div><div>Third</div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $divs = $dom->find('div');
        $this->assertCount(3, $divs);
    }

    public function testInvalidTagNames()
    {
        $html = '<123invalid>Content</123invalid><-tag>More</-tag>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $this->assertNotNull($dom->root);
    }

    public function testAttributesWithoutValues()
    {
        $html = '<input type="checkbox" checked disabled readonly>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $input = $dom->find('input', 0);
        $this->assertNotNull($input);
        $this->assertTrue($input->hasAttribute('checked'));
        $this->assertTrue($input->hasAttribute('disabled'));
    }

    public function testPhpCodeStripping()
    {
        $html = '<div><?php echo "test"; ?><p>Content</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
    }

    public function testWhitespaceHandling()
    {
        $html = "<div>   \n\t  <p>  Content  </p>  \n</div>";
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $p = $dom->find('p', 0);
        $this->assertNotNull($p);
    }

    public function testEmptyHtmlString()
    {
        $dom = $this->parse('');

        $this->assertFalse($dom);
    }

    public function testLargeHtml()
    {
        $items = '';
        for ($i = 0; $i < 100; $i++) {
            $items .= "<li>Item $i</li>";
        }
        $html = "<ul>$items</ul>";
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $listItems = $dom->find('li');
        $this->assertCount(100, $listItems);
    }

    public function testFbtSelfClosingTags()
    {
        // fbt tags are not default - they must be registered by fbt library
        DomForge::registerSelfClosingTags([
            'fbt:enum',
            'fbt:pronoun',
            'fbt:sameparam',
            'fbt:same-param',
        ]);

        $this->assertTrue(DomForge::isSelfClosingTag('fbt:enum'));
        $this->assertTrue(DomForge::isSelfClosingTag('fbt:pronoun'));
        $this->assertTrue(DomForge::isSelfClosingTag('fbt:sameParam')); // converted to lowercase: fbt:sameparam
        $this->assertTrue(DomForge::isSelfClosingTag('fbt:same-param'));

        // Reset for other tests
        DomForge::resetSelfClosingTags();
    }

    public function testNamespacedElements()
    {
        $html = '<fbt:param name="test">Value</fbt:param>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $param = $dom->find('fbt:param', 0);
        $this->assertNotNull($param);
        $this->assertTrue($param->isNamespacedElement());
    }

    public function testCallbackFunctionality()
    {
        $html = '<div><p>Test</p></div>';
        $dom = $this->parse($html);

        $callCount = 0;
        $dom->setCallback(function ($node) use (&$callCount) {
            $callCount++;
        });

        // Trigger callback by getting outerHtml
        $dom->save();

        $this->assertGreaterThan(0, $callCount);

        $dom->removeCallback();
        $this->assertNull($dom->callback);
    }

    public function testDeprecatedMethodAliases()
    {
        $html = '<div><p>Test</p></div>';
        $dom = $this->parse($html);

        $called = false;
        $dom->setCallback(function ($node) use (&$called) {
            $called = true;
        });

        $dom->save();
        $this->assertTrue($called);

        $dom->removeCallback();
        $this->assertNull($dom->callback);
    }

    public function testConstantsAccessible()
    {
        $this->assertEquals(1, DomForge::TYPE_ELEMENT);
        $this->assertEquals(2, DomForge::TYPE_COMMENT);
        $this->assertEquals(3, DomForge::TYPE_TEXT);
        $this->assertEquals(5, DomForge::TYPE_ROOT);
    }

    public function testCharsetDetection()
    {
        $html = '<html><head><meta charset="UTF-8"></head><body>Content</body></html>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $this->assertEquals('UTF-8', $dom->charset);
    }

    public function testSaveToString()
    {
        $html = '<div><p>Content</p></div>';
        $dom = $this->parse($html);

        $output = $dom->save();
        $this->assertContains('<div>', $output);
        $this->assertContains('<p>Content</p>', $output);
    }

    public function testClearMethod()
    {
        $html = '<div><p>Content</p></div>';
        $dom = $this->parse($html);

        $this->assertNotNull($dom->root);
        $this->assertNotEmpty($dom->nodes);

        $dom->clear();

        $this->assertNull($dom->root);
        $this->assertEmpty($dom->nodes);
    }

    public function testBrokenIframeWithAttributes()
    {
        $html = '<div><iframe src="test.html" width="100"><p>Fallback</p></div>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $iframe = $dom->find('iframe', 0);
        $this->assertNotNull($iframe);
    }

    public function testMultipleNestedInvalidStructures()
    {
        $html = '<table><tr><td><div><p>Text<span>More</td></tr></table>';
        $dom = $this->parse($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $table = $dom->find('table', 0);
        $this->assertNotNull($table);
    }

    public function testSiblingSelector()
    {
        $html = '<div><p>First</p><span>Second</span><p>Third</p></div>';
        $dom = $this->parse($html);

        // Adjacent sibling
        $adjacent = $dom->find('p + span');
        $this->assertCount(1, $adjacent);

        // General sibling
        $general = $dom->find('p ~ p');
        $this->assertCount(1, $general);
    }

    public function testAddSelfClosingTag()
    {
        // Add a custom self-closing tag
        DomForge::addSelfClosingTag('custom-tag');

        $dom = new DomForge();
        $this->assertTrue($dom->isSelfClosingTag('custom-tag'));
        $this->assertTrue($dom->isSelfClosingTag('CUSTOM-TAG')); // Case insensitive

        // Clean up
        DomForge::removeSelfClosingTag('custom-tag');
    }

    public function testRemoveSelfClosingTag()
    {
        // Verify br is a self-closing tag by default
        $dom1 = new DomForge();
        $this->assertTrue($dom1->isSelfClosingTag('br'));

        // Remove it
        DomForge::removeSelfClosingTag('br');

        $dom2 = new DomForge();
        $this->assertFalse($dom2->isSelfClosingTag('br'));

        // Restore
        DomForge::addSelfClosingTag('br');
    }

    public function testSetSelfClosingTags()
    {
        DomForge::registerSelfClosingTags([
            'foo',
            'bar',
            'BAZ',
        ]);

        $dom = new DomForge();
        $this->assertTrue($dom->isSelfClosingTag('foo'));
        $this->assertTrue($dom->isSelfClosingTag('bar'));
        $this->assertTrue($dom->isSelfClosingTag('baz'));
        $this->assertTrue($dom->isSelfClosingTag('br'));
    }

    public function testGetSelfClosingTags()
    {
        $tags = DomForge::getSelfClosingTags();

        $this->assertTrue(is_array($tags));
        $this->assertContains('br', $tags);
        $this->assertContains('img', $tags);
        $this->assertContains('input', $tags);
    }

    public function testResetSelfClosingTags()
    {
        // Add a custom tag
        DomForge::addSelfClosingTag('temporary-tag');

        $dom1 = new DomForge();
        $this->assertTrue($dom1->isSelfClosingTag('temporary-tag'));
    }

    public function testFindOne()
    {
        $html = '<div><p>First</p><p>Second</p><p>Third</p></div>';
        $dom = $this->parse($html);

        $p = $dom->findOne('p');
        $this->assertNotNull($p);
        $this->assertEquals('p', $p->tag);
        $this->assertContains('First', $p->innerHtml());

        // Test with nested selector
        $nested = $dom->findOne('div p');
        $this->assertNotNull($nested);
        $this->assertEquals('p', $nested->tag);
    }

    public function testFindOneReturnsNullWhenNotFound()
    {
        $html = '<div><p>Test</p></div>';
        $dom = $this->parse($html);

        $span = $dom->findOne('span');
        $this->assertNull($span);
    }

    public function testFromHtml()
    {
        $html = '<div class="test"><p>Hello World</p></div>';
        $dom = DomForge::fromHtml($html);

        $this->assertInstanceOf(DomForge::class, $dom);
        $div = $dom->findOne('div.test');
        $this->assertNotNull($div);
        $this->assertContains('Hello World', $div->innerHtml());
    }

    public function testFromHtmlWithOptions()
    {
        $html = '<DIV><P>Content</P></DIV>';

        // Test with lowercase = true (default)
        $config1 = (new \dobron\DomForge\Configuration())->setLowercase(true);
        $dom = DomForge::fromHtml($html, $config1);
        $div = $dom->findOne('div');
        $this->assertNotNull($div);
        $this->assertEquals('div', $div->tag);

        // Test with lowercase = false
        $config2 = (new Configuration())->setLowercase(false);
        $dom2 = DomForge::fromHtml($html, $config2);
        $div2 = $dom2->findOne('DIV');
        $this->assertNotNull($div2);
        $this->assertEquals('DIV', $div2->tag);
    }

    public function testConfigurationSelfClosingTags()
    {
        // Reset to make sure we start clean
        DomForge::resetSelfClosingTags();

        // Verify custom tag is not self-closing by default
        $this->assertFalse(DomForge::isSelfClosingTag('my-component'));

        // Use configuration to add custom self-closing tag
        $config = (new Configuration())->setSelfClosingTags(['my-component', 'custom-tag']);
        $dom = DomForge::fromHtml('<div><my-component/></div>', $config);

        // Now the tags should be registered
        $this->assertTrue(DomForge::isSelfClosingTag('my-component'));
        $this->assertTrue(DomForge::isSelfClosingTag('custom-tag'));

        // Clean up
        DomForge::resetSelfClosingTags();
    }

    public function testConfigurationAddSelfClosingTags()
    {
        DomForge::resetSelfClosingTags();

        $config = (new Configuration())
            ->addSelfClosingTags('tag1', 'tag2')
            ->addSelfClosingTags('tag3');

        $tags = $config->getSelfClosingTags();
        $this->assertCount(3, $tags);
        $this->assertContains('tag1', $tags);
        $this->assertContains('tag2', $tags);
        $this->assertContains('tag3', $tags);

        DomForge::fromHtml('<div></div>', $config);

        $this->assertTrue(DomForge::isSelfClosingTag('tag1'));
        $this->assertTrue(DomForge::isSelfClosingTag('tag2'));
        $this->assertTrue(DomForge::isSelfClosingTag('tag3'));

        DomForge::resetSelfClosingTags();
    }

    public function testNodeFindOne()
    {
        $html = '<div><section><p>First</p><p>Second</p></section></div>';
        $dom = $this->parse($html);

        $section = $dom->findOne('section');
        $this->assertNotNull($section);

        $p = $section->findOne('p');
        $this->assertNotNull($p);
        $this->assertContains('First', $p->innerHtml());
    }

    public function testDomTreeEmptyRoot()
    {
        $html = $this->parse('');

        $this->assertFalse($html);
    }

    public function testDomTreeSingleDiv()
    {
        $str = '<div id="div1"></div>';
        $html = $this->parse($str);

        $e = $html->root;
        $this->assertEquals('div1', $e->firstChild()->id);
        $this->assertEquals('div1', $e->lastChild()->id);
        $this->assertNull($e->nextSibling());
        $this->assertNull($e->previousSibling());
        $this->assertEquals('', $e->textContent);
        $this->assertEquals('<div id="div1"></div>', $e->innerHtml);
        $this->assertEquals($str, $e->outerHtml);
    }

    public function testDomTreeNestedDivs()
    {
        $str = '<div id="div1">     <div id="div10"></div>     <div id="div11"></div>     <div id="div12"></div> </div>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $e = $html->find('div#div1', 0);
        $this->assertTrue(isset($e->id));
        $this->assertFalse(isset($e->_not_exist));
        $this->assertEquals('div10', $e->firstChild()->id);
        $this->assertEquals('div12', $e->lastChild()->id);
        $this->assertNull($e->nextSibling());
        $this->assertNull($e->previousSibling());
    }

    public function testDomTreeSiblingNavigation()
    {
        $str = '<div id="div0">     <div id="div00"></div> </div> <div id="div1">     <div id="div10"></div>     <div id="div11"></div>     <div id="div12"></div> </div> <div id="div2"></div>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $e = $html->find('div#div1', 0);
        $this->assertEquals('div10', $e->firstChild()->id);
        $this->assertEquals('div12', $e->lastChild()->id);
        $this->assertEquals('div2', $e->nextSibling()->id);
        $this->assertEquals('div0', $e->previousSibling()->id);

        $e = $html->find('div#div2', 0);
        $this->assertNull($e->firstChild());
        $this->assertNull($e->lastChild());

        $e = $html->find('div#div0 div#div00', 0);
        $this->assertNull($e->firstChild());
        $this->assertNull($e->lastChild());
        $this->assertNull($e->nextSibling());
        $this->assertNull($e->previousSibling());
    }

    public function testDomTreeDeeplyNestedAccess()
    {
        $str = '<div id="div0">     <div id="div00"></div> </div> <div id="div1">     <div id="div10"></div>     <div id="div11">         <div id="div110"></div>         <div id="div111">             <div id="div1110"></div>             <div id="div1111"></div>             <div id="div1112"></div>         </div>         <div id="div112"></div>     </div>     <div id="div12"></div> </div> <div id="div2"></div>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $this->assertEquals('div1', $html->find("#div1", 0)->id);
        $this->assertEquals('div10', $html->find("#div1", 0)->children(0)->id);
        $this->assertEquals('div111', $html->find("#div1", 0)->children(1)->children(1)->id);
        $this->assertEquals('div1112', $html->find("#div1", 0)->children(1)->children(1)->children(2)->id);
    }

    public function testCheckboxAttributes()
    {
        $str = '<form name="form1" method="post" action="">     <input type="checkbox" name="checkbox0" checked value="checkbox0">aaa<br>     <input type="checkbox" name="checkbox1" value="checkbox1">bbb<br>     <input type="checkbox" name="checkbox2" value="checkbox2" checked>ccc<br> </form>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $counter = 0;
        foreach ($html->find('input[type=checkbox]') as $checkbox) {
            if (isset($checkbox->checked)) {
                $this->assertEquals("checkbox$counter", $checkbox->value);
                $counter += 2;
            }
        }

        $counter = 0;
        foreach ($html->find('input[type=checkbox]') as $checkbox) {
            if ($checkbox->checked) {
                $this->assertEquals("checkbox$counter", $checkbox->value);
                $counter += 2;
            }
        }

        $es = $html->find('input[type=checkbox]');
        $es[1]->checked = true;
        $this->assertEquals('<input type="checkbox" name="checkbox1" value="checkbox1" checked>', (string) $es[1]);
        $es[0]->checked = false;
        $this->assertEquals('<input type="checkbox" name="checkbox0" value="checkbox0">', (string) $es[0]);
        $es[0]->checked = true;
        $this->assertEquals('<input type="checkbox" name="checkbox0" checked value="checkbox0">', $es[0]->outerHtml);
    }

    public function testRemoveAttributeBasic()
    {
        $str = '<input type="checkbox" name="checkbox0">' . ' ' . "<input type = \"checkbox\" name = 'checkbox1' value = \"checkbox1\">";
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);
        $e = $html->find('[name=checkbox0]', 0);
        $e->name = null;
        $this->assertEquals('<input type="checkbox">', (string) $e);
        $e->type = null;
        $this->assertEquals('<input>', (string) $e);
    }

    public function testRemoveAttributeWithQuotes()
    {
        $str = '<input type="checkbox" name="checkbox0">' . ' ' . "<input type = \"checkbox\" name = 'checkbox1' value = \"checkbox1\">";
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);
        $e = $html->find('[name=checkbox1]', 0);
        $e->value = null;
        $this->assertEquals("<input type = \"checkbox\" name = 'checkbox1'>", (string) $e);
        $e->type = null;
        $this->assertEquals("<input name = 'checkbox1'>", (string) $e);
        $e->name = null;
        $this->assertEquals('<input>', (string) $e);
    }

    public function testRemoveBooleanAttributes()
    {
        $str = "<input type=\"checkbox\" checked name='checkbox0'>" . ' ' . "<input type=\"checkbox\" name='checkbox1' checked>";
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);
        $e = $html->find('[name=checkbox1]', 0);
        $e->type = null;
        $this->assertEquals("<input name='checkbox1' checked>", (string) $e);
        $e->name = null;
        $this->assertEquals("<input checked>", (string) $e);
        $e->checked = null;
        $this->assertEquals("<input>", (string) $e);
    }

    public function testPlaintextExtraction()
    {
        $html = $this->parse('<b>okok</b>');
        $this->assertEquals('<b>okok</b>', (string) $html);
        $this->assertEquals('okok', $html->textContent);

        $html = $this->parse('<div><b>okok</b></div>');
        $this->assertEquals('<div><b>okok</b></div>', (string) $html);
        $this->assertEquals('okok', $html->textContent);

        $html = $this->parse('<div><b>okok</b>');
        $this->assertEquals('<div><b>okok</b>', (string) $html);
        $this->assertEquals('okok', $html->textContent);
    }

    public function testGetElementMethods()
    {
        $str = '<input type="checkbox" id="checkbox" name="checkbox" value="checkbox" checked> <input type="checkbox" id="checkbox1" name="checkbox1" value="checkbox1"> <input type="checkbox" id="checkbox2" name="checkbox2" value="checkbox2" checked>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $this->assertTrue($html->getElementByTagName('input')->hasAttribute('checked'));
        $this->assertFalse($html->getElementsByTagName('input', 1)->hasAttribute('checked'));
        $this->assertFalse($html->getElementsByTagName('input', 1)->hasAttribute('not_exist'));

        $this->assertEquals($html->find('input', 0)->value, $html->getElementByTagName('input')->getAttribute('value'));
        $this->assertEquals($html->find('input', 1)->value, $html->getElementsByTagName('input', 1)->getAttribute('value'));

        $this->assertEquals($html->find('#checkbox1', 0)->value, $html->getElementById('checkbox1')->getAttribute('value'));
        $this->assertEquals($html->find('#checkbox2', 0)->value, $html->getElementsById('checkbox2', 0)->getAttribute('value'));
    }

    public function testGetSetAttribute()
    {
        $str = '<input type="checkbox" id="checkbox" name="checkbox" value="checkbox" checked>';
        $html = $this->parse($str);

        $e = $html->find('[name=checkbox]', 0);
        $this->assertEquals('checkbox', $e->getAttribute('value'));
        $this->assertTrue($e->getAttribute('checked'));
        $this->assertNull($e->getAttribute('not_exist'));

        $e->setAttribute('value', 'okok');
        $this->assertEquals('<input type="checkbox" id="checkbox" name="checkbox" value="okok" checked>', (string) $e);

        $e->setAttribute('checked', false);
        $this->assertEquals('<input type="checkbox" id="checkbox" name="checkbox" value="okok">', (string) $e);

        $e->setAttribute('checked', true);
        $this->assertEquals('<input type="checkbox" id="checkbox" name="checkbox" value="okok" checked>', (string) $e);

        $e->removeAttribute('value');
        $this->assertEquals('<input type="checkbox" id="checkbox" name="checkbox" checked>', (string) $e);

        $e->removeAttribute('checked');
        $this->assertEquals('<input type="checkbox" id="checkbox" name="checkbox">', (string) $e);
    }

    public function testCamelCaseTraversalMethods()
    {
        $str = '<div id="div1">     <div id="div10"></div>     <div id="div11"></div>     <div id="div12"></div> </div>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $e = $html->find('div#div1', 0);
        $this->assertEquals('div10', $e->firstChild()->getAttribute('id'));
        $this->assertEquals('div12', $e->lastChild()->getAttribute('id'));
        $this->assertNull($e->nextSibling());
        $this->assertNull($e->previousSibling());
    }

    public function testChildrenMethod()
    {
        $str = '<div id="div0">     <div id="div00"></div> </div> <div id="div1">     <div id="div10"></div>     <div id="div11">         <div id="div110"></div>         <div id="div111">             <div id="div1110"></div>             <div id="div1111"></div>             <div id="div1112"></div>         </div>         <div id="div112"></div>     </div>     <div id="div12"></div> </div> <div id="div2"></div>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $this->assertTrue($html->getElementById("div1")->hasAttribute('id'));
        $this->assertFalse($html->getElementById("div1")->hasAttribute('not_exist'));

        $this->assertEquals('div1', $html->getElementById("div1")->getAttribute('id'));
        $this->assertEquals('div10', $html->getElementById("div1")->children(0)->getAttribute('id'));
        $this->assertEquals('div111', $html->getElementById("div1")->children(1)->children(1)->getAttribute('id'));
        $this->assertEquals('div1112', $html->getElementById("div1")->children(1)->children(1)->children(2)->getAttribute('id'));

        $this->assertEquals('div11', $html->getElementsById("div1", 0)->children(1)->id);
        $this->assertEquals('div111', $html->getElementsById("div1", 0)->children(1)->children(1)->getAttribute('id'));
        $this->assertEquals('div1111', $html->getElementsById("div1", 0)->children(1)->children(1)->children(1)->getAttribute('id'));
    }

    public function testSetInnerHtml()
    {
        $html = $this->parse('<div id="test"><p>Original</p></div>');
        $div = $html->findOne('#test');

        $div->innerHtml = '<span>Changed</span>';
        $this->assertContains('Changed', $div->innerHtml());

        // Also works via property
        $this->assertContains('Changed', $div->innerHtml);
    }

    public function testSetOuterHtml()
    {
        $html = $this->parse('<div><p id="target">Original</p></div>');
        $p = $html->findOne('#target');

        $p->outerHtml = '<span>Replaced</span>';
        $this->assertEquals('<span>Replaced</span>', $p->outerHtml());
    }

    public function testModifyAttributeChangesOutput()
    {
        $html = $this->parse('<input type="text" name="field" value="original">');
        $input = $html->findOne('input');

        $input->value = 'modified';
        $this->assertContains('value="modified"', $input->outerHtml());

        $input->setAttribute('placeholder', 'Enter text');
        $this->assertContains('placeholder="Enter text"', $input->outerHtml());
    }

    public function testSetBooleanAttribute()
    {
        $html = $this->parse('<input type="checkbox" name="check">');
        $input = $html->findOne('input');

        $this->assertFalse($input->hasAttribute('checked'));

        $input->checked = true;
        $this->assertTrue($input->hasAttribute('checked'));
        $this->assertContains('checked', $input->outerHtml());

        $input->checked = false;
        $outer = $input->outerHtml();
        $this->assertNotContains('checked', $outer);
    }

    public function testSetCallbackIsCalled()
    {
        $html = $this->parse('<div><p>Test</p><span>More</span></div>');

        $processedTags = [];
        $html->setCallback(function ($node) use (&$processedTags) {
            $processedTags[] = $node->tag;
        });

        // Trigger callback by getting output
        $html->save();

        $this->assertNotEmpty($processedTags);
        $this->assertContains('p', $processedTags);
        $this->assertContains('span', $processedTags);
    }

    public function testCallbackCanModifyNodes()
    {
        $html = $this->parse('<div><a href="http://example.com">Link</a></div>');

        $html->setCallback(function ($node) {
            if ($node->tag === 'a') {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener');
            }
        });

        $this->assertContains('target="_blank"', (string) $html);
        $this->assertContains('rel="noopener"', (string) $html);
    }

    public function testRemoveCallback()
    {
        $html = $this->parse('<div><p>Test</p></div>');

        $callCount = 0;
        $html->setCallback(function ($node) use (&$callCount) {
            $callCount++;
        });

        $html->save();
        $firstCount = $callCount;

        $html->removeCallback();
        $callCount = 0;
        $html->save();

        $this->assertEquals(0, $callCount);
        $this->assertGreaterThan(0, $firstCount);
    }

    public function testCallbackReceivesCorrectNodeTypes()
    {
        $html = $this->parse('<div><p class="text">Content</p></div>');

        $elementNodes = [];
        $html->setCallback(function ($node) use (&$elementNodes) {
            if ($node->isElement()) {
                $elementNodes[] = $node->tag;
            }
        });

        $html->save();

        $this->assertContains('div', $elementNodes);
        $this->assertContains('p', $elementNodes);
    }

    public function testListParsingWithInvalidClosingTag()
    {
        $str = '<ul class="menublock">     </li>         <ul>             <li>                 <a href="http://www.cyberciti.biz/tips/pollsarchive">Polls Archive</a>             </li>         </ul>     </li> </ul>';
        $html = $this->parse($str);

        $ul = $html->find('ul', 0);
        $this->assertEquals('ul', $ul->firstChild()->tag);
    }

    public function testNestedListNavigation()
    {
        $str = '<ul>     <li>Item 1          <ul>             <li>Sub Item 1 </li>             <li>Sub Item 2 </li>         </ul>     </li>     <li>Item 2 </li> </ul>';
        $html = $this->parse($str);

        $this->assertEquals($str, (string) $html);

        $ul = $html->find('ul', 0);
        $this->assertEquals('li', $ul->firstChild()->tag);
        $this->assertEquals('li', $ul->firstChild()->nextSibling()->tag);
    }

    public function testConfigurationDefaults()
    {
        $config = new Configuration();

        $this->assertEquals('UTF-8', $config->getTargetCharset());
        $this->assertTrue($config->isLowercase());
        $this->assertTrue($config->isForceTagsClosed());
        $this->assertTrue($config->shouldRemoveLineBreaks());
        $this->assertEquals("\n", $config->getDefaultBrText());
        $this->assertEquals(' ', $config->getDefaultSpanText());
    }

    public function testConfigurationSetters()
    {
        $config = new Configuration();

        $config
            ->setTargetCharset('ISO-8859-1')
            ->setLowercase(false)
            ->setForceTagsClosed(false)
            ->setRemoveLineBreaks(false)
            ->setDefaultBrText("\r\n")
            ->setDefaultSpanText('_');

        $this->assertEquals('ISO-8859-1', $config->getTargetCharset());
        $this->assertFalse($config->isLowercase());
        $this->assertFalse($config->isForceTagsClosed());
        $this->assertFalse($config->shouldRemoveLineBreaks());
        $this->assertEquals("\r\n", $config->getDefaultBrText());
        $this->assertEquals('_', $config->getDefaultSpanText());
    }

    public function testConfigurationFluentInterface()
    {
        $config = new Configuration();

        $result = $config->setLowercase(true);
        $this->assertSame($config, $result);

        $result = $config->setTargetCharset('UTF-8');
        $this->assertSame($config, $result);
    }

    public function testDomWithConfiguration()
    {
        $config = (new Configuration())
            ->setLowercase(false)
            ->setForceTagsClosed(true);

        $dom = DomForge::fromHtml('<DIV><P>Content</P></DIV>', $config);

        $this->assertSame($config, $dom->getConfiguration());
        $div = $dom->findOne('DIV');
        $this->assertNotNull($div);
        $this->assertEquals('DIV', $div->tag);
    }

    public function testDomGetConfiguration()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $config = $dom->getConfiguration();

        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testDomForgeFactoryWithConfiguration()
    {
        $config = (new Configuration())
            ->setLowercase(false);

        $dom = DomForge::fromHtml('<DIV>Content</DIV>', $config);

        $div = $dom->findOne('DIV');
        $this->assertNotNull($div);
        $this->assertEquals('DIV', $div->tag);
    }

    public function testFromFileWithValidFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'dom_test_');
        file_put_contents($tempFile, '<div id="test"><p>Content from file</p></div>');

        try {
            $dom = DomForge::fromFile($tempFile);
            $this->assertInstanceOf(DomForge::class, $dom);

            $div = $dom->findOne('#test');
            $this->assertNotNull($div);
            $this->assertEquals('test', $div->id);

            $p = $dom->findOne('p');
            $this->assertContains('Content from file', $p->innerHtml());
        } finally {
            unlink($tempFile);
        }
    }

    public function testFromFileWithConfiguration()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'dom_test_');
        file_put_contents($tempFile, '<DIV><P>Content</P></DIV>');

        try {
            $config = (new Configuration())->setLowercase(false);
            $dom = DomForge::fromFile($tempFile, $config);

            $div = $dom->findOne('DIV');
            $this->assertNotNull($div);
            $this->assertEquals('DIV', $div->tag);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFromFileWithNonExistentFile()
    {
        $result = DomForge::fromFile('/path/to/non/existent/file.html');
        $this->assertFalse($result);
    }

    public function testFromFileWithDirectory()
    {
        $result = DomForge::fromFile(sys_get_temp_dir());
        $this->assertFalse($result);
    }

    public function testCreateElement()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $element = $dom->createElement('span');

        $this->assertInstanceOf(Node::class, $element);
        $this->assertEquals('span', $element->tag);
        $this->assertEquals(DomForge::TYPE_ELEMENT, $element->nodetype);
    }

    public function testCreateElementWithContent()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $element = $dom->createElement('p', 'Hello World');

        $this->assertEquals('p', $element->tag);
        $this->assertEquals('Hello World', $element->innerHtml());
        $this->assertContains('<p>Hello World</p>', $element->outerHtml());
    }

    public function testCreateElementWithAttributes()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $element = $dom->createElement('a', 'Click me', ['href' => 'https://example.com', 'class' => 'btn']);

        $this->assertEquals('a', $element->tag);
        $this->assertEquals('https://example.com', $element->getAttribute('href'));
        $this->assertEquals('btn', $element->getAttribute('class'));
        $this->assertContains('href="https://example.com"', $element->outerHtml());
    }

    public function testCreateElementWithBooleanAttribute()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $element = $dom->createElement('input', null, ['type' => 'checkbox', 'checked' => true]);

        $this->assertTrue($element->hasAttribute('checked'));
        $this->assertContains('checked', $element->outerHtml());
    }

    public function testCreateElementSelfClosing()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $element = $dom->createElement('br');

        $this->assertEquals('br', $element->tag);
        $this->assertContains('/>', $element->outerHtml());
    }

    public function testCreateTextNode()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $textNode = $dom->createTextNode('Hello World');

        $this->assertEquals(DomForge::TYPE_TEXT, $textNode->nodetype);
        $this->assertEquals('Hello World', $textNode->outerHtml());
    }

    public function testCreateComment()
    {
        $dom = DomForge::fromHtml('<div></div>');
        $comment = $dom->createComment('This is a comment');

        $this->assertEquals(DomForge::TYPE_COMMENT, $comment->nodetype);
        $this->assertEquals('<!--This is a comment-->', $comment->outerHtml());
    }

    public function testAppendChild()
    {
        $dom = DomForge::fromHtml('<div id="container"></div>');
        $container = $dom->findOne('#container');
        $child = $dom->createElement('span', 'Child content');

        $result = $container->appendChild($child);

        $this->assertSame($child, $result);
        $this->assertSame($container, $child->parent);
        $this->assertContains('<span>Child content</span>', $container->innerHtml());
    }

    public function testRemoveChild()
    {
        $dom = DomForge::fromHtml('<div id="container"><span>Child</span></div>');
        $container = $dom->findOne('#container');
        $span = $dom->findOne('span');

        $removed = $container->removeChild($span);

        $this->assertSame($span, $removed);
        $this->assertNull($span->parent);
        $this->assertNotContains('<span>', $container->innerHtml());
    }

    public function testInsertBefore()
    {
        $dom = DomForge::fromHtml('<ul><li>Second</li></ul>');
        $ul = $dom->findOne('ul');
        $secondLi = $dom->findOne('li');
        $firstLi = $dom->createElement('li', 'First');

        $ul->insertBefore($firstLi, $secondLi);

        $this->assertEquals($firstLi, $ul->firstChild());
        $this->assertEquals($secondLi, $ul->lastChild());
    }

    public function testHasChildren()
    {
        $dom = DomForge::fromHtml('<div><p>Content</p></div><span></span>');
        $div = $dom->findOne('div');
        $span = $dom->findOne('span');

        $this->assertTrue($div->hasChildren());
        $this->assertFalse($span->hasChildren());
    }

    public function testAutomaticReparseAfterSettingInnerHtml()
    {
        $dom = DomForge::fromHtml('<div id="container"><p>Original</p></div>');
        $container = $dom->findOne('#container');

        $this->assertCount(1, $container->children);
        $this->assertEquals('p', $container->firstChild()->tag);

        $container->innerHtml = '<span>New</span><a href="#">Link</a>';

        $this->assertCount(2, $container->children);
        $this->assertEquals('span', $container->firstChild()->tag);
        $this->assertEquals('a', $container->lastChild()->tag);
    }

    public function testAutomaticReparseWithEmptyContent()
    {
        $dom = DomForge::fromHtml('<div id="container"><p>Content</p></div>');
        $container = $dom->findOne('#container');

        $container->innerHtml = '';

        $this->assertCount(0, $container->children);
    }

    public function testAutomaticReparseWithNestedElements()
    {
        $dom = DomForge::fromHtml('<div id="container"></div>');
        $container = $dom->findOne('#container');

        $container->innerHtml = '<ul><li>First</li><li>Second</li></ul>';

        $this->assertCount(1, $container->children);
        $ul = $container->firstChild();
        $this->assertEquals('ul', $ul->tag);
        $this->assertCount(2, $ul->children);
    }

    public function testAutomaticReparseWithTextContent()
    {
        $dom = DomForge::fromHtml('<div id="container"><p>Old</p></div>');
        $container = $dom->findOne('#container');

        $this->assertTrue($container->nodes[0]->isElement());

        $container->innerHtml = 'Just text';

        $this->assertCount(1, $container->nodes);
        $this->assertTrue($container->nodes[0]->isText());
    }

    public function testSettingOuterHtmlUpdatesOutput()
    {
        $dom = DomForge::fromHtml('<div id="parent"><span id="child">Original</span></div>');
        $child = $dom->findOne('#child');

        $child->outerHtml = '<p class="new">New paragraph</p>';

        $this->assertEquals('<p class="new">New paragraph</p>', $child->outerHtml);
    }
}
