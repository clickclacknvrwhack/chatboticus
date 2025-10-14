# ChatBoticus - AI Lead Qualification Chatbot for WordPress

An intelligent WordPress chatbot plugin that qualifies leads using OpenAI's GPT models and routes high-intent conversations to you via Discord threads. Features office hours management, away mode, and human takeover capabilities.

## 🎯 Features

- **AI-Powered Conversations**: Uses OpenAI GPT models to engage visitors naturally
- **Discord Integration**: Creates dedicated threads for each conversation with live reply capability
- **Office Hours Management**: Different behavior during/outside business hours
- **Away Mode**: Collect leads silently during meetings or busy periods
- **Human Takeover**: Jump into conversations directly from Discord
- **Custom Knowledge Base**: Train the bot with your business information
- **Session Management**: Maintains conversation context across interactions
- **Responsive Design**: Mobile-friendly chat widget

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenAI API account
- Discord server and bot
- External Discord listener bot (Node.js, deployed on Railway)

## 🚀 Installation

1. **Upload the Plugin**

2. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Click Foundry ChatBoticus"
   - Click "Activate"

3. **Configure Settings**
   - Go to Settings → Lead Chatbot
   - Fill in all required fields (see Configuration section)

## ⚙️ Configuration

### 1. OpenAI Setup

1. Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
2. Add it to the plugin settings
3. The plugin uses `gpt-4o-mini` by default (cost-effective)

### 2. Discord Setup

#### Create Discord Server & Bot

1. Create a Discord server (your private workspace)
2. Enable Developer Mode: User Settings → Advanced → Developer Mode
3. Create a `#leads` channel
4. Right-click the channel → Copy Channel ID

#### Create Discord Application

1. Go to [Discord Developer Portal](https://discord.com/developers/applications)
2. Click "New Application"
3. Go to "Bot" section
4. Click "Reset Token" and copy the bot token
5. **Critical**: Enable these Privileged Gateway Intents:
   - MESSAGE CONTENT INTENT
   - SERVER MEMBERS INTENT

#### Generate Bot Invite URL

1. Go to OAuth2 → URL Generator
2. Select scopes:
   - `bot`
3. Select permissions:
   - Send Messages
   - Create Public Threads
   - Send Messages in Threads
   - Read Message History
4. Copy the generated URL and use it to invite the bot to your server

### 3. Discord Listener Bot

The WordPress plugin needs a companion Discord bot to listen for human replies and send them back to the website chat.

**Required Bot Code** (Node.js):

```javascript
// Save as index.js
const { Client, GatewayIntentBits } = require('discord.js');
const axios = require('axios');

const DISCORD_BOT_TOKEN = process.env.DISCORD_BOT_TOKEN;
const WORDPRESS_WEBHOOK_URL = process.env.WORDPRESS_WEBHOOK_URL; // e.g., https://yoursite.com/wp-json/alc/v1/discord-message

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent,
  ],
});

client.on('ready', () => {
  console.log(`Logged in as ${client.user.tag}`);
});

client.on('messageCreate', async (message) => {
  // Only process thread messages
  if (!message.channel.isThread()) return;
  
  // Don't process bot messages
  if (message.author.bot) return;
  
  try {
    await axios.post(WORDPRESS_WEBHOOK_URL, {
      thread_id: message.channel.id,
      message: message.content,
      author_is_bot: message.author.bot,
    }, {
      headers: {
        'Authorization': `Bot ${DISCORD_BOT_TOKEN}`,
        'Content-Type': 'application/json',
      },
    });
  } catch (error) {
    console.error('Error forwarding message:', error.message);
  }
});

client.login(DISCORD_BOT_TOKEN);
```

**Deploy to Railway.app** (Recommended):

1. Create account at [Railway.app](https://railway.app)
2. Create new project
3. Add the code above
4. Set environment variables:
   - `DISCORD_BOT_TOKEN` (from Discord Developer Portal)
   - `WORDPRESS_WEBHOOK_URL` (shown in plugin settings: `https://yoursite.com/wp-json/alc/v1/discord-message`)
5. Deploy

**Alternative**: Deploy to Heroku, DigitalOcean, or any Node.js hosting.

### 4. Knowledge Base

You have two options for setting up your knowledge base:

**Option A: Use the included file**
1. Edit the `knowledgebase.txt` file in the plugin folder
2. Replace the placeholder text with your actual business information
3. Save the file

**Option B: Delete the file and use WordPress settings**
1. Delete `knowledgebase.txt` from the plugin folder
2. Use the "Custom Knowledge Base" field in WordPress Admin → Settings → Lead Chatbot
3. Paste your business information directly there

**Knowledge Base Tips**:
- Include your services, pricing, and typical timelines
- List ideal client profiles and qualification questions
- Provide common Q&A
- Describe what makes you different
- The AI will reference this information naturally in conversations

### 5. Plugin Settings

Fill in all fields in WordPress Admin → Settings → Lead Chatbot:

- **Your Name**: How you want to be referred to
- **OpenAI API Key**: From OpenAI platform
- **Greeting Message (Office Hours)**: First message visitors see during business hours
- **After Hours Message**: First message outside office hours
- **Custom Knowledge Base**: (Optional) Add here instead of using file
- **Discord Bot Token**: From Discord Developer Portal
- **Discord Channel ID**: Your #leads channel ID

## 🎮 How to Use

### Quick Controls

In the WordPress admin settings, you'll find quick toggle switches:

- **Chatbot Enabled**: Master on/off switch
- **Away Mode**: Bot works normally but doesn't ping you (great for meetings)

### Office Hours Logic

**Office Hours** (9am-5pm PT, Monday-Friday):
- Bot identifies high-intent leads
- Pings you in Discord for immediate response
- You can jump in and take over the conversation

**Outside Office Hours**:
- Bot still qualifies leads
- Creates Discord thread but doesn't ping
- You can follow up when back online

**Away Mode** (anytime):
- Bot continues qualifying
- No pings sent (silent mode)
- Review conversations when ready

### Responding to Leads

When a high-intent lead appears:

1. Discord will create a thread in your #leads channel
2. If during office hours (and not in away mode), you'll get pinged
3. Simply reply in the thread
4. Your message appears instantly in the website chat
5. The visitor sees "Typing..." change to your message
6. Continue the conversation naturally

### High-Intent Signals

The bot considers these signals as high-intent:
- Mentions budget, pricing, or cost
- Says things like "interested," "want to talk," "call me"
- Discusses timeline
- After 8+ messages (long engagement)

## 🔧 Customization

### Change AI Model

Edit `chatbot.php`, find the OpenAI API call:

```php
'model' => 'gpt-4o-mini', // Change to 'gpt-4' for better quality (higher cost)
```

### Adjust Office Hours

Edit the `alc_is_office_hours()` function:

```php
// Monday-Friday, 9am-5pm PT
$is_weekday = $day >= 1 && $day <= 5;
$is_office_hours = $hour >= 9 && $hour < 17;
```

### Customize Styling

Edit `chatbot.css` to match your brand:

```css
#alc-chat-toggle {
    background: #007bff; /* Change color */
}
```

### Modify Qualification Logic

Edit the system message in `chatbot.php` function `alc_handle_chat()` to adjust how the bot qualifies leads.

## 📊 How It Works

1. **Visitor opens chat** → Bot greets with appropriate message
2. **Conversation begins** → GPT model responds using your knowledge base
3. **Bot qualifies lead** → Asks about industry, needs, budget, timeline
4. **Discord thread created** → First message creates thread in #leads
5. **High-intent detected** → Bot pings you (if in office hours & not away)
6. **You respond** → Discord listener forwards your reply to website
7. **Seamless handoff** → Visitor continues chat with you

## 🔒 Security Notes

- Never commit your actual `knowledgebase.txt` file
- Keep `.env` files out of version control
- Regenerate Discord bot token if exposed
- Use environment variables for sensitive data in production
- WordPress nonces protect AJAX endpoints

## 🐛 Troubleshooting

**Bot not responding:**
- Check OpenAI API key is valid and has credits
- Check browser console for JavaScript errors

**Discord integration not working:**
- Verify bot token and channel ID
- Ensure bot has proper permissions
- Check that Discord listener bot is running
- Verify webhook URL in listener bot matches your site

**Human replies not showing:**
- Confirm Discord listener bot is deployed and running
- Check Railway/Heroku logs for errors
- Verify webhook authorization header

**Chat widget not appearing:**
- Check that "Chatbot Enabled" is checked in settings
- Clear WordPress cache
- Check browser console for errors

## 📝 License

This plugin is released under GPL v2 or later.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 💬 Support

For issues and questions, please open an issue on GitHub.

## 🙏 Credits

Built with OpenAI GPT models and Discord API.
