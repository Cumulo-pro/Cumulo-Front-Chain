# Roller-Front-Chain
<h2>The web interface for Dymension's blockchain</h2>

<h2 id="introduction">Introduction</h2>
<p>Publishing blockchain data as web content, whether to build a dashboard, a block explorer, or simply display certain data on the web, is often tedious, especially for front-end developers with no knowledge of technologies such as blockchain.</p>
<p>In addition, additional infrastructure is usually necessary to access such data, such as dedicated servers, RPC nodes, installation of services like Prometheus, Grafana, etc.</p>


<p>Roller Front-Chain extracts data from some sources:</p>
<ul>
<li>Prometheus</li>
<li> RPC Node</li>
<li>CLI node with scripts</li>
</ul>

<h3>What does Roller Front Chain solve? </h3>
<p>With this small framework we aim to solve the development time and infrastructure for publishing blockchain data on the web. Making it easy for designers to create content with a few lines of html code.</p>
<h3>A quick example of what we can do</h3>
<p>Imagine you are a front-end designer and you want to integrate the data from a blockchain into a fun and attractive Dashboard for the public. You don't have to worry about anything but the design. Enter the necessary functions to display the data in the header of the page and the id to the html elements with which you want to associate the metric values. That's it! Are you ready to go?</p>
<h2 id="start">Getting Started</h2>
<p>1) Add the following code inside the header &lt;head&gt;:</p>
<code>&lt;script type=&quot;text/javascript&quot; src=&quot;imp_data.js&quot;&gt;&lt;/script&gt;</code>
<br>
<p>2) Add the function code that corresponds to the metric to display, inside the &lt;head&gt; header:</p>
<p><code>
  &lt;script&gt;<br>
  //Function call (metric name)<br>
  showdata(&quot;block&quot;);<br>
&lt;/script&gt;</code></p>
<p>3) In case you enter a metric that is frequently updated you can add the following line:</p>
<code><pre> 
&lt;script&gt;
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;setInterval(function(){
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;showdata(&quot;block&quot;);
 },5000); // delay 5 seg
&lt;/script&gt;
</pre></code>
<p>NOTE: You can see all available metrics <a href="#metrics">here</a>.</p>
<p>4) Add the id attribute with the name of the function to the tag you want to use to display the metric value:</p>
<code>&lt;p&gt;Froopyland Height: &lt;span id=&quot;block&quot;&gt;&lt;/span&gt;&lt;/p&gt;</strong></code>
<p>You can see the full example <a href="basic_example_frontchain.html">here</a>.</p>
