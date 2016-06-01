<?xml version="1.0" encoding="utf-8"?>

<!--
The MIT License

Copyright 2016 OEAW/ACDH.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
-->

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:msxsl="urn:schemas-microsoft-com:xslt" exclude-result-prefixes="msxsl"
    xmlns:sru="http://www.loc.gov/zing/srw/">
    <xsl:param name="uri"/>
    <xsl:param name="type"/>
    <xsl:param name="style"/>
    <xsl:param name="requested-context"/>
  <xsl:output method="text" indent="no"/>
  <xsl:template match="/">
    <xsl:for-each select="sru:scanResponse/sru:terms/sru:term/sru:extraTermData/sru:terms/sru:term">
      <xsl:text>$configName["</xsl:text>
      <xsl:value-of select="sru:value"/>
      <xsl:text>"] = array(</xsl:text>
      <xsl:text>"endPoint" =&gt; "</xsl:text>
      <xsl:value-of select="$uri"/>
      <xsl:text>", </xsl:text>
      <xsl:text>"name" =&gt; "</xsl:text>
      <xsl:value-of select="sru:value"/>
      <xsl:text>", </xsl:text>
      <xsl:text>"displayText" =&gt; "</xsl:text>
      <xsl:value-of select="sru:displayTerm"/>
      <xsl:text>", </xsl:text>
      <xsl:text>"type" =&gt; "</xsl:text>
      <xsl:value-of select="$type"/>
      <xsl:text>"</xsl:text>
      <xsl:if test="$style != ''">
        <xsl:text>, style" =&gt; "</xsl:text>
        <xsl:value-of select="$style"/>
        <xsl:text>"</xsl:text>
      </xsl:if>
      <xsl:choose>
        <xsl:when test="../../../sru:value != 'default'">
          <xsl:text>, "context" =&gt; "</xsl:text>
          <xsl:value-of select="substring-after(sru:value, concat(../../../sru:value,':'))"/>
          <xsl:text>"</xsl:text>
        </xsl:when>
        <xsl:when test="$requested-context != ''">
          <xsl:text>, "context" =&gt; "</xsl:text>
          <xsl:value-of select="substring-after(sru:value, concat($requested-context,':'))"/>
          <xsl:text>"</xsl:text>
        </xsl:when>
      </xsl:choose>
      <xsl:text>);
    </xsl:text>
    </xsl:for-each>
  </xsl:template>
</xsl:stylesheet>
