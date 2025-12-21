// @ts-ignore - whatsapp-web.js é CommonJS
import pkg from 'whatsapp-web.js';
const { Client, LocalAuth, MessageMedia } = pkg;
// @ts-ignore - qrcode-terminal não tem tipos
import qrcode from 'qrcode-terminal';
import axios from 'axios';
import dotenv from 'dotenv';
import { join } from 'path';
import { createServer } from 'http';
import { URL } from 'url';
import * as fs from 'fs';
import * as os from 'os';

dotenv.config();

// Tipos do whatsapp-web.js
type Message = any;

class WhatsAppBot {
  private client: any;
  private laravelApiUrl: string;
  private instanceName: string;
  private qrCodeSent: boolean = false;
  private isReady: boolean = false;
  private httpServer: any = null;
  private botPort: number;

  constructor() {
    this.instanceName = process.env.INSTANCE_NAME || 'bot-instance';
    this.laravelApiUrl = process.env.LARAVEL_API_URL || 'http://localhost:8000';
    this.botPort = parseInt(process.env.BOT_PORT || '3001');

    // Configurar cliente WhatsApp com autenticação local
    this.client = new Client({
      authStrategy: new LocalAuth({
        clientId: this.instanceName,
        dataPath: join(process.cwd(), '.wwebjs_auth'),
      }),
      puppeteer: {
        headless: true,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-accelerated-2d-canvas',
          '--no-first-run',
          '--no-zygote',
          '--disable-gpu',
        ],
      },
    });

    this.setupEventHandlers();
  }

  private setupEventHandlers() {
    // QR Code gerado
    this.client.on('qr', async (qr: string) => {
      console.log('📱 QR Code gerado! Escaneie com o WhatsApp:');
      qrcode.generate(qr, { small: true });

      // Converter QR code para base64 e enviar para Laravel
      try {
        const qrBase64 = await this.qrToBase64(qr);
        await this.sendToLaravel('qrcode', {
          instance_name: this.instanceName,
          qrcode: qrBase64,
          code: qr,
        });
        this.qrCodeSent = true;
      } catch (error: any) {
        console.error('Erro ao enviar QR Code para Laravel:', error.message);
      }
    });

    // Cliente pronto
    this.client.on('ready', async () => {
      console.log('✅ WhatsApp conectado e pronto!');
      this.isReady = true;
      this.qrCodeSent = false;

      const info = this.client.info;
      console.log(`📱 Conectado como: ${info?.pushname || info?.wid?.user || 'Desconhecido'}`);

      await this.sendToLaravel('connection-status', {
        instance_name: this.instanceName,
        status: 'connected',
        phone: info?.wid?.user,
        name: info?.pushname,
      });

      await this.sendToLaravel('bot-status', {
        instance_name: this.instanceName,
        status: 'started',
      });
    });

    // Cliente autenticado
    this.client.on('authenticated', () => {
      console.log('🔐 Autenticado com sucesso!');
    });

    // Falha na autenticação
    this.client.on('auth_failure', async (msg: string) => {
      console.error('❌ Falha na autenticação:', msg);
      await this.sendToLaravel('connection-status', {
        instance_name: this.instanceName,
        status: 'auth_failure',
        error: msg,
      });
    });

    // Cliente desconectado
    this.client.on('disconnected', async (reason: string) => {
      console.log('❌ WhatsApp desconectado:', reason);
      this.isReady = false;

      await this.sendToLaravel('connection-status', {
        instance_name: this.instanceName,
        status: 'disconnected',
        reason: reason,
      });
    });

    // Mensagem recebida
    this.client.on('message', async (message: Message) => {
      await this.handleIncomingMessage(message);
    });

    // Erro
    this.client.on('error', (error: Error) => {
      console.error('❌ Erro no cliente WhatsApp:', error.message);
    });
  }

  private async qrToBase64(qr: string): Promise<string> {
    try {
      // @ts-ignore
      const QRCode = await import('qrcode');
      const qrBuffer = await QRCode.default.toBuffer(qr);
      return `data:image/png;base64,${qrBuffer.toString('base64')}`;
    } catch (error) {
      // Se não conseguir converter, retornar o QR code como está
      return Buffer.from(qr).toString('base64');
    }
  }

  private async sendToLaravel(endpoint: string, data: any) {
    try {
      const response = await axios.post(`${this.laravelApiUrl}/api/${endpoint}`, data, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        timeout: 10000,
      });
      console.log(`✅ Dados enviados para Laravel (${endpoint}):`, response.status);
    } catch (error: any) {
      // Sempre mostrar erro para debug
      if (error.code === 'ECONNREFUSED') {
        console.warn(`⚠️  Laravel não está acessível em ${this.laravelApiUrl} - Verifique se está rodando`);
      } else {
        console.error(`❌ Erro ao enviar para Laravel (${endpoint}):`, error.message);
        if (error.response) {
          console.error('Resposta do servidor:', error.response.data);
        }
      }
    }
  }

  private async handleIncomingMessage(message: Message) {
    try {
      // Ignorar mensagens próprias e status
      if (message.fromMe || message.isStatus) {
        return;
      }

      let messageText = message.body || '';
      const messageId = message.id._serialized;
      const timestamp = message.timestamp * 1000; // Converter para milissegundos

      // Extrair informações do contato diretamente do message.from
      // Formato: "5511999999999@s.whatsapp.net" ou "5511999999999@c.us"
      let contactName = message.from || 'Desconhecido';
      let contactNumber = message.from || '';

      // Extrair número do formato "5511999999999@s.whatsapp.net"
      const numberMatch = message.from?.match(/^(\d+)@/);
      if (numberMatch) {
        contactNumber = numberMatch[1];
        contactName = numberMatch[1]; // Usar número como nome padrão
      }

      // Tentar obter nome do contato de forma opcional (sem bloquear se falhar)
      // Usar notifyName se disponível (nome salvo no WhatsApp)
      if (message.notifyName) {
        contactName = message.notifyName;
      }

      // Verificar se é mensagem de áudio e converter para texto
      // WhatsApp usa 'ptt' para notas de voz (push-to-talk)
      const isAudioMessage = message.hasMedia && (
        message.type === 'ptt' || 
        message.type === 'audio' ||
        (message.mimetype && message.mimetype.startsWith('audio/'))
      );
      
      if (isAudioMessage) {
        let tempAudioPath: string | null = null;
        try {
          console.log('🎤 Mensagem de áudio detectada, convertendo para texto...');
          
          // Tentar baixar áudio com timeout e tratamento de erro melhorado
          let media = null;
          try {
            media = await Promise.race([
              message.downloadMedia(),
              new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Timeout ao baixar áudio')), 30000)
              )
            ]) as any;
          } catch (downloadError: any) {
            console.error('❌ Erro ao baixar áudio:', downloadError.message);
            messageText = ''; // Será ignorada pela validação posterior
            media = null; // Garantir que media seja null
          }
          
          // Só processar se conseguiu baixar o áudio
          if (media && media.data) {
            try {
              // Extrair base64 do formato data:audio/ogg;base64,...
              let audioBase64 = media.data;
              if (audioBase64.includes(',')) {
                audioBase64 = audioBase64.split(',')[1];
              }
              
              // Validar se o base64 não está vazio
              if (!audioBase64 || audioBase64.trim().length === 0) {
                throw new Error('Áudio base64 vazio');
              }
              
              // Determinar extensão do arquivo baseado no mimetype
              const mimeType = media.mimetype || 'audio/ogg; codecs=opus';
              let extension = 'ogg';
              if (mimeType.includes('mpeg') || mimeType.includes('mp3')) {
                extension = 'mp3';
              } else if (mimeType.includes('wav')) {
                extension = 'wav';
              } else if (mimeType.includes('ogg')) {
                extension = 'ogg';
              }
              
              // Criar arquivo temporário
              tempAudioPath = join(os.tmpdir(), `whatsapp_audio_${Date.now()}_${Math.random().toString(36).substring(7)}.${extension}`);
              
              // Decodificar base64 e salvar em arquivo
              const audioBuffer = Buffer.from(audioBase64, 'base64');
              
              // Validar tamanho do buffer
              if (audioBuffer.length === 0) {
                throw new Error('Buffer de áudio vazio');
              }
              
              fs.writeFileSync(tempAudioPath, audioBuffer);
              
              console.log(`💾 Áudio salvo temporariamente: ${tempAudioPath} (${audioBuffer.length} bytes)`);
              
              // Ler arquivo e converter para base64 novamente para enviar
              const fileBuffer = fs.readFileSync(tempAudioPath);
              const fileBase64 = fileBuffer.toString('base64');
              
              // Enviar para Laravel converter em texto
              const transcriptionResponse = await axios.post(
                `${this.laravelApiUrl}/api/elevenlabs/speech-to-text`,
                {
                  audio: fileBase64,
                  mimetype: mimeType,
                  extension: extension,
                },
                {
                  headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                  },
                  timeout: 120000, // 2 minutos para transcrição
                }
              );

              if (transcriptionResponse.data.success && transcriptionResponse.data.data?.text) {
                const transcribedText = transcriptionResponse.data.data.text;
                // Validar que a transcrição não está vazia
                if (transcribedText && transcribedText.trim().length > 0) {
                  messageText = transcribedText;
                  console.log(`📝 Áudio convertido para texto: ${messageText}`);
                } else {
                  console.warn('⚠️  Transcrição retornou texto vazio, ignorando mensagem');
                  messageText = ''; // Será ignorada pela validação posterior
                }
              } else {
                console.warn('⚠️  Não foi possível converter áudio para texto, mensagem será ignorada');
                messageText = ''; // Será ignorada pela validação posterior
              }
            } catch (processError: any) {
              console.error('❌ Erro ao processar áudio:', processError.message);
              messageText = ''; // Será ignorada pela validação posterior
            }
          }
        } catch (audioError: any) {
          console.error('❌ Erro ao converter áudio para texto:', audioError.message);
          messageText = ''; // Será ignorada pela validação posterior
        } finally {
          // Limpar arquivo temporário
          if (tempAudioPath && fs.existsSync(tempAudioPath)) {
            try {
              fs.unlinkSync(tempAudioPath);
              console.log(`🗑️  Arquivo temporário removido: ${tempAudioPath}`);
            } catch (cleanupError: any) {
              console.warn(`⚠️  Erro ao remover arquivo temporário: ${cleanupError.message}`);
            }
          }
        }
      }

      // VALIDAÇÃO CRÍTICA: Ignorar completamente mensagens vazias
      // Não processar, não salvar no Laravel e não verificar fluxos
      if (!messageText || typeof messageText !== 'string' || messageText.trim().length === 0) {
        console.warn('⚠️  Mensagem vazia ignorada - não será processada nem salva');
        return; // Retornar imediatamente sem processar
      }

      const trimmedText = messageText.trim();
      
      // Validar que a mensagem não é apenas espaços em branco após trim
      if (trimmedText.length === 0) {
        console.warn('⚠️  Mensagem contém apenas espaços em branco - ignorada');
        return;
      }

      // Enviar mensagem para Laravel APÓS processar áudio
      await this.sendToLaravel('messages', {
        instance_name: this.instanceName,
        message_id: messageId,
        from: message.from,
        to: message.to,
        message: messageText,
        timestamp: timestamp,
        direction: 'incoming',
        contact_name: contactName,
        contact_number: contactNumber,
        raw_message: {
          type: message.type,
          hasMedia: message.hasMedia,
          isGroupMsg: message.isGroupMsg,
        },
      });

      console.log(`📨 Mensagem recebida de ${contactName}: ${messageText}`);

      // Verificar se há fluxo configurado
      await this.checkFlows(message.from, messageText);
    } catch (error: any) {
      console.error('Erro ao processar mensagem:', error.message);
      // Tentar enviar pelo menos informações básicas para o Laravel
      try {
        await this.sendToLaravel('messages', {
          instance_name: this.instanceName,
          message_id: message.id?._serialized || Date.now().toString(),
          from: message.from || 'unknown',
          message: message.body || '',
          timestamp: Date.now(),
          direction: 'incoming',
          error: error.message,
        });
      } catch (fallbackError: any) {
        console.error('Erro ao enviar mensagem de fallback:', fallbackError.message);
      }
    }
  }

  private async checkFlows(contact: string, messageText: string) {
    try {
      // Buscar fluxos ativos do Laravel
      const response = await axios.get(
        `${this.laravelApiUrl}/api/flows/active`,
        {
          headers: {
            'Accept': 'application/json',
          },
          timeout: 5000,
        }
      );

      const flows = response.data.data || [];

      for (const flow of flows) {
        if (this.matchFlow(flow, messageText)) {
          await this.executeFlow(flow, contact, messageText);
          break;
        }
      }
    } catch (error: any) {
      // Se não houver fluxos ou Laravel não estiver rodando, não faz nada
      if (error.response?.status !== 404 && error.code !== 'ECONNREFUSED') {
        console.error('Erro ao verificar fluxos:', error.message);
      }
    }
  }

  private matchFlow(flow: any, messageText: string): boolean {
    if (!flow.is_active) return false;

    // VALIDAÇÃO CRÍTICA: Nunca processar mensagens vazias, mesmo com catch_all
    if (!messageText || typeof messageText !== 'string' || messageText.trim().length === 0) {
      return false;
    }
    
    // Verificar também se não é mensagem de erro/vazia
    const trimmedText = messageText.trim();
    if (trimmedText === '[Mensagem vazia]' || trimmedText === '[Erro ao processar áudio]' || 
        trimmedText === '[Áudio não disponível]' || trimmedText === '[Áudio não transcrito]') {
      return false;
    }

    const triggers = flow.triggers || [];
    const text = messageText.toLowerCase();

    // Se não houver triggers, não executar
    if (triggers.length === 0) {
      return false;
    }

    for (const trigger of triggers) {
      // Gatilho "catch_all" - qualquer mensagem
      if (trigger.type === 'catch_all') {
        return true;
      }
      
      // Validação para outros tipos
      if (!trigger.value) continue;
      
      if (trigger.type === 'exact' && text === trigger.value.toLowerCase()) {
        return true;
      }
      if (trigger.type === 'contains' && text.includes(trigger.value.toLowerCase())) {
        return true;
      }
      if (trigger.type === 'starts_with' && text.startsWith(trigger.value.toLowerCase())) {
        return true;
      }
    }

    return false;
  }

  private async executeFlow(flow: any, contact: string, messageText: string) {
    try {
      const actions = flow.actions || [];

      for (const action of actions) {
        try {
          if (action.type === 'send_message') {
            // Validar conteúdo antes de tentar enviar
            if (!action.content || typeof action.content !== 'string' || action.content.trim().length === 0) {
              console.warn('⚠️  Ação send_message ignorada: conteúdo vazio');
              continue;
            }
            await this.sendMessage(contact, action.content);
          } else if (action.type === 'wait') {
            await new Promise(resolve => setTimeout(resolve, action.duration || 1000));
          } else if (action.type === 'ai_response') {
            await this.sendAIResponse(contact, messageText, action);
          } else if (action.type === 'conditional') {
            await this.executeConditionalAction(contact, messageText, action);
          }
        } catch (actionError: any) {
          // Se for erro de mensagem vazia, apenas logar e continuar
          if (actionError.message && actionError.message.includes('vazia')) {
            console.warn(`⚠️  Ação ${action.type} ignorada: ${actionError.message}`);
          } else {
            // Para outros erros, propagar para o catch externo
            throw actionError;
          }
        }
      }

      // Registrar execução do fluxo
      await this.sendToLaravel('flow-executions', {
        flow_id: flow.id,
        contact: contact,
        trigger_message: messageText,
      });
    } catch (error: any) {
      console.error('Erro ao executar fluxo:', error.message);
    }
  }

  /**
   * Verifica se o conteúdo deve ser enviado como texto ao invés de áudio
   * Detecta chaves PIX, links, códigos, portfólios, etc.
   */
  private shouldSendAsText(content: string): boolean {
    if (!content || typeof content !== 'string') {
      return false;
    }

    const text = content.toLowerCase();
    const originalText = content;
    
    // 1. Verificar links explícitos (sempre enviar como texto)
    if (/https?:\/\/[^\s]+/i.test(content) || /www\.[^\s]+/i.test(content)) {
      console.log('📝 Detectado link, enviando como texto');
      return true;
    }
    
    // 2. Verificar e-mails (sempre enviar como texto)
    if (/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(content)) {
      console.log('📝 Detectado e-mail, enviando como texto');
      return true;
    }
    
    // 3. Palavras-chave críticas que sempre indicam conteúdo sensível
    const criticalKeywords = [
      /\b(chave\s*pix|chavepix|link\s*pix|linkpix)\b/i,
      /\b(portfólio|portfolio)\b/i,
      /\b(qr\s*code|qrcode)\b/i,
      /\b(código\s*de\s*barras|codigo\s*de\s*barras)\b/i,
    ];
    
    for (const keyword of criticalKeywords) {
      if (keyword.test(content)) {
        console.log('📝 Detectado palavra-chave crítica, enviando como texto');
        return true;
      }
    }
    
    // 4. Detectar chaves PIX (códigos alfanuméricos longos)
    // Chave aleatória PIX: 32 caracteres alfanuméricos
    // Pode ter hífens ou estar em um bloco de texto
    const pixKeyPatterns = [
      /\b[A-Z0-9]{32,}\b/, // Chave aleatória PIX (32+ caracteres)
      /\b[0-9]{11}\b/, // CPF (11 dígitos)
      /\b[0-9]{14}\b/, // CNPJ (14 dígitos)
      /\+\s*55\s*[0-9]{10,11}\b/, // Telefone brasileiro com código do país
    ];
    
    // 5. Verificar se tem palavra relacionada a PIX/pagamento + código
    const pixRelatedWords = [
      'pix', 'chave', 'pagamento', 'transferência', 'link', 'código', 'codigo',
      'enviar', 'segue', 'aqui está', 'link pix', 'chave pix'
    ];
    
    const hasPixRelatedWord = pixRelatedWords.some(word => text.includes(word));
    const hasLongCode = pixKeyPatterns.some(pattern => pattern.test(originalText));
    
    // Se tem palavra relacionada E código longo, provavelmente é PIX/link
    if (hasPixRelatedWord && hasLongCode) {
      console.log('📝 Detectado palavra relacionada a PIX/pagamento + código, enviando como texto');
      return true;
    }
    
    // 6. Códigos muito longos sozinhos (provavelmente são chaves ou códigos)
    if (/\b[A-Z0-9]{25,}\b/.test(originalText)) {
      console.log('📝 Detectado código muito longo, enviando como texto');
      return true;
    }
    
    // 7. Verificar padrão "[CHAVE PIX]" ou similar com código após
    if (/\[.*?(?:chave|pix|link|url).*?\]/i.test(content)) {
      console.log('📝 Detectado padrão [CHAVE PIX] ou similar, enviando como texto');
      return true;
    }

    return false;
  }

  /**
   * Verifica se uma linha contém conteúdo sensível (chaves PIX reais)
   * Apenas detecta chaves PIX quando realmente há uma chave, não links genéricos
   * @param line Linha a verificar
   * @param sensitiveKeywords Lista opcional de palavras-chave sensíveis configuráveis
   */
  private isLineSensitive(line: string, sensitiveKeywords: string[] = []): boolean {
    if (!line || !line.trim()) return false;
    
    const lineLower = line.toLowerCase();
    const lineTrimmed = line.trim();
    
    // PRIORIDADE 1: Verificar palavras-chave sensíveis configuráveis (do fluxo)
    // Isso tem prioridade máxima - se configurado, sempre marca como sensível
    if (sensitiveKeywords && sensitiveKeywords.length > 0) {
      for (const keyword of sensitiveKeywords) {
        if (!keyword || keyword.trim().length === 0) continue;
        
        const keywordLower = keyword.toLowerCase().trim();
        
        // Verificar se a linha contém a palavra-chave (case-insensitive)
        // Usar includes para capturar parcialmente (ex: "CHAVE PIX:" contém "chave pix")
        if (lineLower.includes(keywordLower)) {
          console.log(`📝 [CONFIGURADO] Detectado conteúdo sensível: "${keyword}"`);
          console.log(`   Linha completa: ${lineTrimmed.substring(0, 80)}${lineTrimmed.length > 80 ? '...' : ''}`);
          return true; // SEMPRE retornar true se encontrar palavra-chave configurada
        }
      }
    }
    
    // PRIORIDADE 2: Verificar e-mails
    if (/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(line)) {
      return true;
    }
    
    // PRIORIDADE 3: Detectar padrões de chave PIX mais amplos
    // Padrão 1: [CHAVE PIX] ou [CHAVE_PIX] (placeholder entre colchetes)
    if (/\[.*?(?:chave\s*pix|chave_pix|pix).*?\]/i.test(line)) {
      return true;
    }
    
    // Padrão 2: *CHAVE PIX:* ou *CHAVE_PIX:* (formato markdown/negrito)
    if (/\*.*?(?:chave\s*pix|chave_pix|pix).*?\*:?/i.test(line)) {
      return true;
    }
    
    // Padrão 3: CHAVE_PIX_FICTICIA ou CHAVE_PIX_QUALQUER_COISA (placeholder com underscore)
    if (/\bCHAVE[_\s]?PIX[_\s]?[A-Z0-9_]+/i.test(line)) {
      return true;
    }
    
    // Padrão 4: Qualquer linha que contenha "chave pix" ou "chavepix" seguido de dois pontos
    // Isso captura: "chave pix:", "chave pix :", "*CHAVE PIX:*", "CHAVE PIX: valor", etc.
    if (/\b(?:chave\s*pix|chavepix|link\s*pix|linkpix)\s*:?\s*/i.test(line)) {
      // Se tem "chave pix" seguido de qualquer coisa (código, placeholder, etc), é sensível
      return true;
    }
    
    // Padrão 5: "CHAVE PIX: valor" ou "chave pix: valor" (com dois pontos e valor após)
    if (/\b(?:chave\s*pix|chavepix)\s*:\s*.+/i.test(line)) {
      return true;
    }
    
    // Verificar se tem palavras-chave relacionadas a PIX
    const pixKeywords = [
      /\b(segue\s*o?\s*link?\s*pix|segue\s*o?\s*pix|chave\s*pix|link\s*pix)\b/i,
      /\b(envio\s+o?\s*link?\s*pix|envio\s+o?\s*pix)\b/i,
      /\b(aqui\s+está\s+o?\s*link?\s*pix|aqui\s+está\s+o?\s*pix)\b/i,
      /\b(enviar\s+chave|enviar\s+pix|segue\s+chave|segue\s+pix)\b/i,
    ];
    
    const hasPixKeyword = pixKeywords.some(pattern => pattern.test(line));
    
    if (hasPixKeyword) {
      // Se tem palavra-chave PIX, verificar se também tem código ou placeholder
      const hasPixKey = /\b[A-Z0-9\-]{25,}\b/.test(line) || 
                        /\b[A-Z0-9]{32,}\b/.test(line) ||
                        /\[.*?(?:chave|pix).*?\]/i.test(line) ||
                        /\*.*?(?:chave|pix).*?\*/i.test(line) ||
                        /\bCHAVE[_\s]?PIX[_\s]?[A-Z0-9_]+/i.test(line);
      
      // Se tem palavra-chave PIX + (código ou placeholder), é sensível
      if (hasPixKey) {
        return true;
      }
    }
    
    // Detectar chave PIX real (código longo com contexto de PIX)
    const hasPixContext = /\b(pix|chave\s*pix|link\s*pix)\b/i.test(line);
    const hasLongCode = /\b[A-Z0-9\-]{32,}\b/.test(line);
    
    if (hasPixContext && hasLongCode) {
      return true;
    }
    
    return false;
  }

  /**
   * Divide o conteúdo em partes: sensível (texto) e não sensível (áudio)
   * Retorna array de objetos { text, isSensitive }
   * @param content Conteúdo a dividir
   * @param sensitiveKeywords Lista opcional de palavras-chave sensíveis configuráveis
   */
  private splitSensitiveContent(content: string, sensitiveKeywords: string[] = []): Array<{ text: string; isSensitive: boolean }> {
    if (!content || typeof content !== 'string') {
      return [{ text: content, isSensitive: false }];
    }

    // PRIORIDADE: Se temos palavras-chave configuráveis, tentar dividir por trechos específicos
    // Exemplo: "Perfeito, segue a chave pix: 709.488.144-46 para o pagamento."
    // Se "chave pix: 709.488.144-46" estiver configurado, deve dividir em:
    // - "Perfeito, segue a " (áudio)
    // - "chave pix: 709.488.144-46" (texto)
    // - " para o pagamento." (áudio)
    
    if (sensitiveKeywords && sensitiveKeywords.length > 0) {
      const parts = this.splitBySensitiveKeywords(content, sensitiveKeywords);
      if (parts.length > 1) {
        // Encontrou trechos sensíveis, retornar divisão
        return this.combineAdjacentParts(parts);
      }
    }
    
    // Se não encontrou trechos sensíveis configuráveis, usar método por linhas
    return this.splitSensitiveContentByLines(content, sensitiveKeywords);
  }
  
  /**
   * Divide conteúdo procurando por palavras-chave sensíveis configuráveis
   * Extrai apenas o trecho que contém a palavra-chave + valor
   * Exemplo: se configurado "chave pix: 709.488.144-46", extrai apenas esse trecho
   */
  private splitBySensitiveKeywords(content: string, sensitiveKeywords: string[]): Array<{ text: string; isSensitive: boolean }> {
    const parts: Array<{ text: string; isSensitive: boolean }> = [];
    let remainingContent = content;
    let foundAny = false;
    
    // Ordenar palavras-chave por tamanho (maior primeiro) para pegar matches mais específicos
    const sortedKeywords = [...sensitiveKeywords].sort((a, b) => b.length - a.length);
    
    // Processar cada palavra-chave
    for (const keyword of sortedKeywords) {
      if (!keyword || keyword.trim().length === 0) continue;
      
      const keywordTrimmed = keyword.trim();
      const keywordLower = keywordTrimmed.toLowerCase();
      const contentLower = remainingContent.toLowerCase();
      
      // Procurar ocorrência da palavra-chave (case-insensitive)
      const keywordIndex = contentLower.indexOf(keywordLower);
      
      if (keywordIndex !== -1) {
        foundAny = true;
        
        // Encontrar o início e fim do trecho sensível
        const beforeSensitive = remainingContent.substring(0, keywordIndex);
        const sensitiveStart = keywordIndex;
        let sensitiveEnd = keywordIndex + keywordTrimmed.length;
        
        // Se a palavra-chave contém ":" e termina com valor (ex: "chave pix: 709.488.144-46")
        // usar o tamanho exato da palavra-chave configurada
        // Se a palavra-chave termina com ":" (ex: "chave pix:"), procurar valor após
        
        if (keywordTrimmed.includes(':')) {
          const colonIndex = keywordTrimmed.indexOf(':');
          const afterColonInKeyword = keywordTrimmed.substring(colonIndex + 1).trim();
          
          // Se a palavra-chave já tem valor após ":" (ex: "chave pix: 709.488.144-46")
          if (afterColonInKeyword.length > 0) {
            // Usar o tamanho exato da palavra-chave configurada
            sensitiveEnd = sensitiveStart + keywordTrimmed.length;
          } else {
            // Palavra-chave termina com ":" (ex: "chave pix:"), procurar valor após no texto
            const afterColonInText = remainingContent.substring(sensitiveStart + colonIndex + 1).trim();
            
            // Procurar valor: pode ser código, URL, número formatado, etc.
            // Padrões: "709.488.144-46" (CPF), "https://exemplo.com", "ABC123"
            const valueMatch = afterColonInText.match(/^(\S+(?:\.\S+)*(?:\-\S+)*)/) || // Número formatado
                              afterColonInText.match(/^(https?:\/\/[^\s]+)/i) || // URL
                              afterColonInText.match(/^(\S+)/); // Qualquer valor sem espaço
            
            if (valueMatch) {
              const value = valueMatch[1];
              // Verificar se parece ser um valor (não é palavra comum)
              const looksLikeValue = /^https?:\/\//i.test(value) || // URL
                                    /^[0-9\.\-\/]+$/.test(value) || // Número formatado (CPF, CNPJ)
                                    (/^[0-9A-Z_\-\.]+$/i.test(value) && value.length >= 5); // Código alfanumérico
              
              if (looksLikeValue) {
                // Incluir espaço antes do valor se houver
                const spaceBefore = remainingContent.substring(sensitiveStart + colonIndex + 1, sensitiveStart + colonIndex + 2) === ' ' ? 1 : 0;
                sensitiveEnd = sensitiveStart + colonIndex + 1 + spaceBefore + value.length;
              }
            }
          }
        }
        
        // Adicionar parte antes (não sensível)
        if (beforeSensitive.trim().length > 0) {
          parts.push({ text: beforeSensitive, isSensitive: false });
        }
        
        // Adicionar parte sensível
        const sensitivePart = remainingContent.substring(sensitiveStart, sensitiveEnd);
        if (sensitivePart.length > 0) {
          parts.push({ text: sensitivePart, isSensitive: true });
          console.log(`📝 [TEXTO] Trecho sensível extraído: "${sensitivePart}"`);
        }
        
        // Continuar processando o restante
        remainingContent = remainingContent.substring(sensitiveEnd);
        
        // Processar recursivamente o restante (pode haver mais ocorrências)
        const remainingParts = this.splitBySensitiveKeywords(remainingContent, sensitiveKeywords);
        parts.push(...remainingParts);
        
        return parts; // Retornar após processar
      }
    }
    
    // Se encontrou trechos sensíveis, adicionar o restante como não sensível
    if (foundAny && remainingContent.trim().length > 0) {
      parts.push({ text: remainingContent, isSensitive: false });
    } else if (!foundAny) {
      // Não encontrou, retornar vazio para usar método por linhas
      return [];
    }
    
    return parts;
  }
  
  /**
   * Divide conteúdo sensível por linhas (método original)
   */
  private splitSensitiveContentByLines(content: string, sensitiveKeywords: string[] = []): Array<{ text: string; isSensitive: boolean }> {
    const lines = content.split('\n');
    const lineParts: Array<{ text: string; isSensitive: boolean }> = [];
    
    let markNextAsSensitive = false;
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      let isSensitive = this.isLineSensitive(line, sensitiveKeywords);
      
      if (isSensitive) {
        markNextAsSensitive = true;
        console.log(`📝 Linha ${i + 1} marcada como sensível: ${line.substring(0, 60)}...`);
      } else if (markNextAsSensitive) {
        const trimmedLine = line.trim();
        if (trimmedLine.length > 0) {
          const isUrl = /^https?:\/\//i.test(trimmedLine);
          const isCode = /^[0-9A-Z_\-]+$/.test(trimmedLine) && trimmedLine.length > 5;
          const isShortValue = trimmedLine.length < 30 && !/[.!?]$/.test(trimmedLine);
          const startsWithLowercaseOrNumber = /^[a-z0-9]/.test(trimmedLine);
          const isAlphanumericCode = /^[A-Z0-9_\-\.]+$/i.test(trimmedLine) && trimmedLine.length > 10;
          
          if (isUrl || isCode || (isShortValue && startsWithLowercaseOrNumber) || isAlphanumericCode) {
            isSensitive = true;
            console.log(`📝 Linha ${i + 1} marcada como sensível (continuação): ${trimmedLine.substring(0, 60)}...`);
            markNextAsSensitive = true;
          } else {
            markNextAsSensitive = false;
          }
        } else {
          if (i < lines.length - 1 && lines[i + 1].trim().length === 0) {
            markNextAsSensitive = false;
          }
        }
      }
      
      lineParts.push({ 
        text: line, 
        isSensitive 
      });
    }

    // Combinar linhas adjacentes com o mesmo tipo (sensível ou não)
    return this.combineAdjacentParts(lineParts);
  }
  
  /**
   * Combina partes adjacentes do mesmo tipo (sensível ou não)
   */
  private combineAdjacentParts(lineParts: Array<{ text: string; isSensitive: boolean }>): Array<{ text: string; isSensitive: boolean }> {
    const parts: Array<{ text: string; isSensitive: boolean }> = [];
    
    for (let i = 0; i < lineParts.length; i++) {
      const current = lineParts[i];
      
      if (parts.length === 0) {
        // Primeira parte
        parts.push({ text: current.text, isSensitive: current.isSensitive });
      } else {
        const lastPart = parts[parts.length - 1];
        
        // Se o tipo é o mesmo, combinar
        if (lastPart.isSensitive === current.isSensitive) {
          // Se a última parte não termina com quebra de linha e a atual não começa com espaço, adicionar espaço
          if (!lastPart.text.endsWith('\n') && !current.text.startsWith(' ') && !current.text.startsWith('\n')) {
            lastPart.text += ' ' + current.text;
          } else {
            lastPart.text += current.text.startsWith('\n') ? current.text : '\n' + current.text;
          }
        } else {
          // Nova parte
          parts.push({ text: current.text, isSensitive: current.isSensitive });
        }
      }
    }

    // Limpar partes vazias
    const cleanedParts = parts
      .map(part => ({ text: part.text.trim(), isSensitive: part.isSensitive }))
      .filter(part => part.text.length > 0);

    // Se não encontrou nada sensível, retorna tudo como não sensível
    if (cleanedParts.length === 0) {
      return [{ text: lineParts.map(p => p.text).join('\n'), isSensitive: false }];
    }

    return cleanedParts;
  }

  /**
   * Extrai triggers de imagem do texto (ex: {imagem_1}, {img001}, {img_1})
   * Aceita qualquer formato dentro de chaves como trigger
   * Retorna array com os nomes das imagens encontradas (sem as chaves)
   */
  private extractImageTriggers(text: string): string[] {
    // Padrão flexível: qualquer texto dentro de chaves {texto}
    // Aceitar qualquer conteúdo alfanumérico (incluindo números no início)
    const imageTriggerRegex = /\{([^}]+)\}/g;
    const matches = [...text.matchAll(imageTriggerRegex)];
    
    if (!matches || matches.length === 0) {
      console.log('🔍 Nenhum trigger de imagem encontrado no texto');
      return [];
    }
    
    // Extrair os nomes dos triggers (sem as chaves)
    // Filtrar apenas triggers que parecem ser nomes de arquivo (alfanumérico com underscore, hífen ou números)
    const triggers = matches
      .map(match => match[1].trim())
      .filter(trigger => {
        // Aceitar qualquer trigger que seja alfanumérico (incluindo números no início como img001)
        const isValid = /^[a-z0-9_\-]+$/i.test(trigger);
        if (!isValid) {
          console.log(`⚠️  Trigger ignorado (não é alfanumérico): {${trigger}}`);
        }
        return isValid;
      });
    
    console.log(`🔍 Triggers de imagem extraídos: ${triggers.join(', ')}`);
    return triggers;
  }

  /**
   * Remove triggers de imagem do texto
   * Remove qualquer padrão {texto} que seja alfanumérico
   */
  private removeImageTriggers(text: string): string {
    // Remover qualquer padrão {texto} alfanumérico (triggers de imagem)
    // Usar o mesmo padrão da extração para garantir consistência
    const cleaned = text.replace(/\{[a-z0-9_\-]+\}/gi, (match) => {
      console.log(`🗑️  Removendo trigger: ${match}`);
      return '';
    }).trim();
    console.log(`🧹 Texto após remover triggers: "${cleaned}"`);
    return cleaned;
  }

  /**
   * Busca uma imagem na pasta /assets com diferentes extensões
   * Retorna o caminho completo do arquivo se encontrado, null caso contrário
   */
  private findImageInAssets(imageName: string): string | null {
    const assetsPath = join(process.cwd(), 'assets');
    const extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    
    console.log(`🔍 Buscando imagem "${imageName}" em: ${assetsPath}`);
    console.log(`📂 Diretório atual (process.cwd()): ${process.cwd()}`);
    
    // Verificar se a pasta assets existe
    if (!fs.existsSync(assetsPath)) {
      console.warn(`⚠️  Pasta /assets não encontrada em: ${assetsPath}`);
      // Tentar caminho alternativo (relativo ao arquivo)
      const altPath = join(__dirname, '..', 'assets');
      if (fs.existsSync(altPath)) {
        console.log(`✅ Pasta assets encontrada em caminho alternativo: ${altPath}`);
        // Continuar com o caminho alternativo
        return this.searchImageInPath(altPath, imageName, extensions);
      }
      return null;
    }
    
    console.log(`✅ Pasta assets encontrada: ${assetsPath}`);
    
    // Listar arquivos na pasta para debug
    try {
      const files = fs.readdirSync(assetsPath);
      console.log(`📁 Arquivos em assets: ${files.join(', ')}`);
    } catch (err) {
      console.warn(`⚠️  Erro ao listar arquivos: ${err}`);
    }
    
    // Tentar cada extensão
    return this.searchImageInPath(assetsPath, imageName, extensions);
  }

  /**
   * Busca imagem em um caminho específico
   */
  private searchImageInPath(path: string, imageName: string, extensions: string[]): string | null {
    for (const ext of extensions) {
      const imagePath = join(path, `${imageName}.${ext}`);
      console.log(`   Tentando: ${imagePath}`);
      if (fs.existsSync(imagePath)) {
        console.log(`✅ Imagem encontrada: ${imagePath}`);
        return imagePath;
      }
    }
    
    console.warn(`⚠️  Imagem não encontrada: ${imageName} (tentou extensões: ${extensions.join(', ')})`);
    return null;
  }

  /**
   * Envia uma imagem via WhatsApp
   */
  private async sendImage(contact: string, imagePath: string): Promise<void> {
    try {
      // Verificar se está pronto e se o cliente ainda existe
      if (!this.isReady) {
        throw new Error('WhatsApp não está conectado');
      }
      
      // Verificar se o cliente ainda está válido
      if (!this.client || !this.client.info) {
        throw new Error('Sessão do WhatsApp foi fechada');
      }

      // Ler arquivo de imagem
      const imageBuffer = fs.readFileSync(imagePath);
      const imageBase64 = imageBuffer.toString('base64');
      
      // Determinar mimetype baseado na extensão
      const ext = imagePath.toLowerCase().split('.').pop();
      let mimetype: string;
      let filename: string;
      
      switch (ext) {
        case 'png':
          mimetype = 'image/png';
          filename = 'image.png';
          break;
        case 'jpg':
        case 'jpeg':
          mimetype = 'image/jpeg';
          filename = 'image.jpg';
          break;
        case 'gif':
          mimetype = 'image/gif';
          filename = 'image.gif';
          break;
        case 'webp':
          mimetype = 'image/webp';
          filename = 'image.webp';
          break;
        default:
          mimetype = 'image/png';
          filename = 'image.png';
      }

      // Formatar chatId
      let chatId = contact;
      if (!contact.includes('@s.whatsapp.net') && !contact.includes('@c.us') && !contact.includes('@lid')) {
        let number = contact.replace(/@.*$/, '').replace(/[^\d+]/g, '');
        if (!number.startsWith('+')) {
          if (number.startsWith('55')) {
            number = '+' + number;
          } else if (number.length >= 10) {
            number = '+55' + number;
          }
        }
        chatId = `${number.replace('+', '')}@s.whatsapp.net`;
      }

      console.log(`📷 Enviando imagem para ${chatId}: ${imagePath}`);

      // Criar MessageMedia e enviar
      // @ts-ignore
      const imageMedia = new MessageMedia(mimetype, imageBase64, filename);
      const sentMessage = await this.client.sendMessage(chatId, imageMedia);

      // Enviar para Laravel
      await this.sendToLaravel('messages', {
        instance_name: this.instanceName,
        message_id: sentMessage.id._serialized,
        from: `${this.instanceName}@bot`,
        to: contact,
        message: `[Imagem] ${imagePath}`,
        timestamp: Date.now(),
        direction: 'outgoing',
      });

      console.log(`✅ Imagem enviada para ${chatId}`);
    } catch (error: any) {
      console.error('❌ Erro ao enviar imagem:', error.message);
      throw error;
    }
  }

  /**
   * Envia imagens baseadas em uma lista de triggers
   * Usado para enviar imagens DEPOIS do áudio/texto
   */
  private async sendImagesFromTriggers(contact: string, imageTriggers: string[]): Promise<void> {
    if (imageTriggers.length === 0) {
      return;
    }
    
    console.log(`🖼️  Enviando ${imageTriggers.length} imagem(ns): ${imageTriggers.join(', ')}`);
    
    // Enviar cada imagem encontrada
    for (const imageName of imageTriggers) {
      console.log(`🔍 Procurando imagem: ${imageName}`);
      const imagePath = this.findImageInAssets(imageName);
      
      if (imagePath) {
        try {
          console.log(`📤 Enviando imagem: ${imagePath}`);
          await this.sendImage(contact, imagePath);
          // Pequeno delay entre imagens
          await new Promise(resolve => setTimeout(resolve, 500));
        } catch (error: any) {
          console.error(`❌ Erro ao enviar imagem ${imageName}:`, error.message);
        }
      } else {
        console.warn(`⚠️  Imagem ${imageName} não encontrada em /assets`);
      }
    }
  }

  private async sendAIResponse(contact: string, userMessage: string, action: any) {
    try {
      if (!this.isReady) {
        throw new Error('WhatsApp não está conectado');
      }

      const prompt = action.prompt || 'Responda de forma amigável e útil: {message}';
      const provider = action.provider || 'ollama';
      const model = action.model || null;
      const showTyping = action.show_typing !== false; // Por padrão mostra "digitando..."
      const useAudio = action.use_audio === true; // Se deve gerar áudio em vez de texto
      const voiceId = action.voice_id || null; // Voice ID do ElevenLabs (opcional)
      const useContext = action.use_context === true; // Se deve usar contexto da conversa
      
      // Extrair palavras-chave sensíveis configuráveis
      let sensitiveKeywords: string[] = [];
      if (action.sensitive_keywords) {
        if (Array.isArray(action.sensitive_keywords)) {
          sensitiveKeywords = action.sensitive_keywords;
        } else if (typeof action.sensitive_keywords === 'string') {
          // Se for string, separar por vírgula
          sensitiveKeywords = action.sensitive_keywords.split(',').map((k: string) => k.trim()).filter((k: string) => k.length > 0);
        }
      }
      
      if (sensitiveKeywords.length > 0) {
        console.log(`📝 Palavras-chave sensíveis configuradas: ${sensitiveKeywords.join(', ')}`);
      }

      // Formatar chatId
      let chatId = contact;
      if (!contact.includes('@s.whatsapp.net') && !contact.includes('@c.us') && !contact.includes('@lid')) {
        let number = contact.replace(/@.*$/, '').replace(/[^\d+]/g, '');
        if (!number.startsWith('+')) {
          if (number.startsWith('55')) {
            number = '+' + number;
          } else if (number.length >= 10) {
            number = '+55' + number;
          }
        }
        chatId = `${number.replace('+', '')}@s.whatsapp.net`;
      }

      // Mostrar "digitando..." se configurado
      if (showTyping) {
        await this.showTyping(chatId);
      }

      // Buscar conversation_id se use_context estiver ativo
      let conversationId = null;
      if (useContext) {
        try {
          // Normalizar contato para busca (remover @lid, @s.whatsapp.net, etc)
          let normalizedContact = contact;
          if (contact.includes('@')) {
            // Extrair apenas o número antes do @
            const match = contact.match(/^(\d+)@/);
            if (match) {
              normalizedContact = match[1];
            }
          }
          
          // Buscar conversa pelo contato (tentar com formato original e normalizado)
          const contactsToTry = [contact, normalizedContact];
          
          for (const contactToTry of contactsToTry) {
            try {
              const conversationResponse = await axios.get(
                `${this.laravelApiUrl}/api/conversations`,
                {
                  params: {
                    contact: contactToTry,
                    instance_name: this.instanceName,
                  },
                  headers: {
                    'Accept': 'application/json',
                  },
                  timeout: 10000, // Aumentado para 10 segundos
                }
              );
              
              if (conversationResponse.data?.data?.length > 0) {
                conversationId = conversationResponse.data.data[0].id;
                console.log(`✅ Contexto encontrado: conversation_id=${conversationId} para contato ${contactToTry}`);
                break;
              }
            } catch (err: any) {
              // Continuar tentando próximo formato
              continue;
            }
          }
          
          if (!conversationId) {
            console.warn(`⚠️  Conversa não encontrada para contato: ${contact} (tentou também: ${normalizedContact})`);
          }
        } catch (error: any) {
          console.warn('⚠️  Não foi possível buscar conversation_id:', error.message);
        }
      }

      // Gerar resposta com IA via Laravel
      let response;
      try {
        response = await axios.post(
          `${this.laravelApiUrl}/api/ai/generate`,
          {
            prompt: prompt,
            message: userMessage,
            provider: provider,
            model: model,
            conversation_id: conversationId,
            use_context: useContext,
          },
          {
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            timeout: 90000, // 90 segundos para IA (aumentado de 60)
          }
        );
      } catch (axiosError: any) {
        // Verificar se é timeout
        if (axiosError.code === 'ECONNABORTED' || axiosError.message.includes('timeout')) {
          console.error('⏱️  Timeout ao gerar resposta com IA (90s)');
          throw new Error('TIMEOUT_IA');
        }
        throw axiosError;
      }

      if (response.data.success && response.data.data?.response) {
        let aiResponse = response.data.data.response;
        
        // VALIDAÇÃO CRÍTICA: Verificar se a resposta da IA não está vazia
        if (!aiResponse || typeof aiResponse !== 'string' || aiResponse.trim().length === 0) {
          console.warn('⚠️  Resposta da IA está vazia, não será enviada');
          throw new Error('A resposta da IA está vazia');
        }
        
        console.log(`🤖 Resposta da IA recebida: "${aiResponse}"`);
        
        // Extrair triggers de imagem ANTES de processar o texto
        const imageTriggers = this.extractImageTriggers(aiResponse);
        
        // Remover triggers do texto para enviar o áudio/texto primeiro
        let cleanedResponse = this.removeImageTriggers(aiResponse);
        
        // VALIDAÇÃO: Após remover triggers, verificar se ainda há conteúdo
        // Se não houver texto mas houver imagens, ainda podemos enviar as imagens
        // Mas não devemos tentar enviar texto vazio
        
        // Dividir conteúdo em partes sensíveis e não sensíveis
        // Passar palavras-chave sensíveis configuráveis do fluxo
        // Garantir que sensitiveKeywords está definido
        const keywordsToUse = sensitiveKeywords || [];
        
        // Se há texto para enviar (após remover triggers)
        if (cleanedResponse && cleanedResponse.trim().length > 0) {
          const parts = this.splitSensitiveContent(cleanedResponse, keywordsToUse);
          
          // Se tem apenas uma parte e não é sensível, pode enviar tudo como áudio
          if (parts.length === 1 && !parts[0].isSensitive && useAudio) {
            await this.sendAudioFromText(contact, cleanedResponse, voiceId);
          } else {
            // Enviar cada parte separadamente
            for (const part of parts) {
              if (!part.text.trim()) continue; // Pular partes vazias
              
              if (part.isSensitive) {
                // Parte sensível sempre como texto
                console.log(`📝 Enviando parte sensível como texto: ${part.text.substring(0, 50)}...`);
                await this.sendMessage(contact, part.text);
              } else {
                // Parte não sensível: enviar como áudio se configurado, senão como texto
                if (useAudio) {
                  console.log(`🎵 Enviando parte como áudio: ${part.text.substring(0, 50)}...`);
                  await this.sendAudioFromText(contact, part.text, voiceId);
                } else {
                  await this.sendMessage(contact, part.text);
                }
              }
              
              // Pequeno delay entre mensagens para não sobrecarregar
              if (parts.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 500));
              }
            }
          }
        }
        
        // AGORA enviar as imagens DEPOIS do áudio/texto
        if (imageTriggers.length > 0) {
          console.log(`🖼️  Enviando ${imageTriggers.length} imagem(ns) após o áudio/texto...`);
          await this.sendImagesFromTriggers(contact, imageTriggers);
        } else if (!cleanedResponse || cleanedResponse.trim().length === 0) {
          // Se não há texto E não há imagens, a resposta está realmente vazia
          console.warn('⚠️  Resposta da IA está vazia (sem texto e sem imagens), não será enviada');
          throw new Error('A resposta da IA está completamente vazia');
        }
      } else {
        throw new Error('Erro ao gerar resposta com IA: resposta não encontrada');
      }
    } catch (error: any) {
      console.error('Erro ao gerar resposta com IA:', error.message);
      
      // Verificar se a sessão ainda está ativa antes de tentar enviar mensagem
      if (!this.isReady) {
        console.warn('⚠️  WhatsApp desconectado, não é possível enviar mensagem de erro');
        return;
      }
      
      // Verificar se é timeout específico
      if (error.message === 'TIMEOUT_IA') {
        console.warn('⏱️  IA demorou muito para responder. Tentando enviar mensagem de timeout...');
      }
      
      // Enviar mensagem de erro se configurado e sessão estiver ativa
      if (action.error_message) {
        try {
          // Verificar novamente se está pronto antes de enviar
          if (this.isReady) {
            await this.sendMessage(contact, action.error_message);
          } else {
            console.warn('⚠️  Sessão fechada durante o processamento, não foi possível enviar mensagem de erro');
          }
        } catch (sendError: any) {
          // Se falhar ao enviar, apenas logar (não propagar erro)
          console.error('❌ Erro ao enviar mensagem de erro:', sendError.message);
          if (sendError.message.includes('Session closed') || sendError.message.includes('page has been closed')) {
            console.warn('⚠️  Sessão do WhatsApp foi fechada. O bot pode precisar ser reiniciado.');
          }
        }
      } else {
        // Se não tem mensagem de erro configurada, tentar enviar mensagem padrão
        try {
          if (this.isReady) {
            await this.sendMessage(contact, 'Desculpe, não consegui processar sua mensagem no momento. Tente novamente em instantes.');
          }
        } catch (sendError: any) {
          console.error('❌ Erro ao enviar mensagem padrão de erro:', sendError.message);
        }
      }
    }
  }

  private async showTyping(chatId: string) {
    try {
      // Simular "digitando..." no WhatsApp
      // O whatsapp-web.js não tem método direto para typing indicator
      // Aguardamos um tempo para simular o processamento da IA
      // Isso ajuda a evitar bloqueios do WhatsApp ao não responder instantaneamente
      
      // Aguardar um tempo mínimo para simular processamento (1.5 segundos)
      // Isso dá a impressão de que o bot está "pensando" antes de responder
      await new Promise(resolve => setTimeout(resolve, 1500));
      
      console.log(`⏳ Simulando "digitando..." para ${chatId}`);
    } catch (error: any) {
      // Ignorar erros de "digitando..."
      console.warn('Não foi possível simular "digitando...":', error.message);
    }
  }

  async sendMessage(contact: string, message: string) {
    try {
      // VALIDAÇÃO CRÍTICA: Não permitir mensagens vazias
      if (!message || typeof message !== 'string' || message.trim().length === 0) {
        console.warn('⚠️  Tentativa de enviar mensagem vazia bloqueada');
        throw new Error('Não é possível enviar mensagens vazias');
      }
      
      // Verificar se está pronto e se o cliente ainda existe
      if (!this.isReady) {
        throw new Error('WhatsApp não está conectado');
      }
      
      // Verificar se o cliente ainda está válido
      if (!this.client || !this.client.info) {
        throw new Error('Sessão do WhatsApp foi fechada');
      }

      let chatId = contact;

      // Se já está no formato correto (@s.whatsapp.net ou @c.us), usar diretamente
      if (contact.includes('@s.whatsapp.net') || contact.includes('@c.us')) {
        chatId = contact;
      } else if (contact.includes('@lid')) {
        // @lid é formato de grupo/link - tentar usar diretamente ou extrair número
        // Para grupos, podemos tentar usar o ID do grupo diretamente
        chatId = contact;
        console.log(`⚠️  Tentando enviar para grupo/link: ${chatId}`);
      } else {
        // Limpar e formatar o número
        let number = contact;
        
        // Remover qualquer sufixo @
        number = number.replace(/@.*$/, '');
        
        // Remover caracteres não numéricos exceto +
        number = number.replace(/[^\d+]/g, '');
        
        // Validar se tem pelo menos alguns dígitos
        if (number.length < 10) {
          throw new Error(`Número inválido: ${contact}. Número muito curto após limpeza.`);
        }
        
        // Se não começar com +, assumir que é número brasileiro
        if (!number.startsWith('+')) {
          // Se começar com 55 (Brasil), adicionar +
          if (number.startsWith('55')) {
            number = '+' + number;
          } else if (number.length >= 10) {
            // Assumir número brasileiro sem código do país
            number = '+55' + number;
          }
        }
        
        // Formatar para o formato do WhatsApp
        chatId = `${number.replace('+', '')}@s.whatsapp.net`;
      }

      console.log(`📤 Enviando mensagem para ${chatId}: ${message.substring(0, 50)}...`);

      const sentMessage = await this.client.sendMessage(chatId, message);

      // Enviar para Laravel
      await this.sendToLaravel('messages', {
        instance_name: this.instanceName,
        message_id: sentMessage.id._serialized,
        from: `${this.instanceName}@bot`,
        to: contact,
        message: message,
        timestamp: Date.now(),
        direction: 'outgoing',
      });

      console.log(`✅ Mensagem enviada para ${chatId}`);
      return sentMessage;
    } catch (error: any) {
      console.error('❌ Erro ao enviar mensagem:', error.message);
      console.error('   Contato original:', contact);
      console.error('   Detalhes do erro:', error.stack || error);
      throw error;
    }
  }

  private async sendAudioFromText(contact: string, text: string, voiceId: string | null = null) {
    try {
      // Verificar se está pronto e se o cliente ainda existe
      if (!this.isReady) {
        throw new Error('WhatsApp não está conectado');
      }
      
      // Verificar se o cliente ainda está válido
      if (!this.client || !this.client.info) {
        throw new Error('Sessão do WhatsApp foi fechada');
      }

      console.log(`🎵 Gerando áudio para: ${text.substring(0, 50)}...`);

      // Gerar áudio via Laravel
      const audioResponse = await axios.post(
        `${this.laravelApiUrl}/api/elevenlabs/text-to-speech`,
        {
          text: text,
          voice_id: voiceId,
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          timeout: 60000, // 60 segundos para gerar áudio
        }
      );

      if (!audioResponse.data.success || !audioResponse.data.data?.audio) {
        throw new Error('Erro ao gerar áudio');
      }

      // Obter áudio e formato detectado
      let audioBase64 = audioResponse.data.data.audio;
      const detectedFormat = audioResponse.data.data.format || 'unknown';
      
      console.log(`📦 Formato detectado: ${detectedFormat}`);
      
      // Validar se o base64 não está vazio
      if (!audioBase64 || typeof audioBase64 !== 'string' || audioBase64.trim().length === 0) {
        throw new Error('Áudio base64 vazio ou inválido');
      }

      // Limpar o base64: remover espaços, quebras de linha e caracteres inválidos
      audioBase64 = audioBase64.trim().replace(/\s/g, '');

      // Validar formato base64 básico
      const base64Regex = /^[A-Za-z0-9+/]*={0,2}$/;
      if (!base64Regex.test(audioBase64)) {
        throw new Error('Formato base64 inválido');
      }

      // Decodificar e re-codificar para garantir integridade do áudio
      let audioBuffer: Buffer;
      let finalFormat: string = detectedFormat;
      try {
        audioBuffer = Buffer.from(audioBase64, 'base64');
        
        // Validar tamanho do buffer
        if (audioBuffer.length === 0) {
          throw new Error('Buffer de áudio vazio após decodificação');
        }

        // Validar tamanho máximo (WhatsApp aceita até ~16MB, mas recomendamos menor)
        const maxSizeBytes = 15 * 1024 * 1024; // 15MB
        if (audioBuffer.length > maxSizeBytes) {
          throw new Error(`Áudio muito grande: ${(audioBuffer.length / 1024 / 1024).toFixed(2)}MB (máximo: 15MB)`);
        }

        // Validar formato do áudio baseado no formato detectado e magic bytes
        const oggMagicBytes = Buffer.from([0x4F, 0x67, 0x67, 0x53]);
        const firstBytes = audioBuffer.slice(0, 4);
        const firstBytesHex = firstBytes.toString('hex');
        const isOggOpus = audioBuffer.length >= 4 && firstBytes.equals(oggMagicBytes);
        const isMp3 = firstBytesHex === '49443304' || firstBytesHex.startsWith('fffb') || firstBytesHex.startsWith('fff3');
        
        // Determinar formato final baseado em magic bytes e formato detectado
        if (isMp3) {
          finalFormat = 'mp3'; // Magic bytes indicam MP3
        } else if (isOggOpus) {
          finalFormat = 'ogg_opus'; // Magic bytes indicam OGG Opus
        }
        // Caso contrário, usar o formato detectado pela API
        
        if (finalFormat === 'mp3') {
          console.warn('⚠️  Áudio recebido é MP3. WhatsApp pode não aceitar bem MP3 como nota de voz.');
          console.warn('   Tentando enviar como MP3 primeiro...');
        } else if (finalFormat === 'ogg_opus') {
          console.log(`✅ Arquivo OGG Opus válido detectado (${(audioBuffer.length / 1024).toFixed(2)}KB)`);
        } else {
          console.warn('⚠️  Formato desconhecido ou Opus sem container OGG');
          console.warn(`   Primeiros bytes: ${firstBytesHex}, Formato detectado: ${finalFormat}`);
        }

        // Re-codificar para garantir base64 limpo
        audioBase64 = audioBuffer.toString('base64');
      } catch (decodeError: any) {
        console.error('❌ Erro ao decodificar base64:', decodeError.message);
        throw new Error(`Erro ao processar áudio: ${decodeError.message}`);
      }

      // Formatar chatId
      let chatId = contact;
      if (!contact.includes('@s.whatsapp.net') && !contact.includes('@c.us') && !contact.includes('@lid')) {
        let number = contact.replace(/@.*$/, '').replace(/[^\d+]/g, '');
        if (!number.startsWith('+')) {
          if (number.startsWith('55')) {
            number = '+' + number;
          } else if (number.length >= 10) {
            number = '+55' + number;
          }
        }
        chatId = `${number.replace('+', '')}@s.whatsapp.net`;
      }

      console.log(`📤 Enviando áudio para ${chatId} (${audioBuffer.length} bytes)`);

      // Determinar mimetype e extensão baseado no formato detectado
      let mimetype: string;
      let filename: string;
      
      if (finalFormat === 'mp3') {
        // MP3 - tentar enviar como MP3
        mimetype = 'audio/mpeg';
        filename = 'audio.mp3';
        console.log('📤 Enviando como MP3 (WhatsApp pode não aceitar bem como nota de voz)');
      } else if (finalFormat === 'ogg_opus') {
        // OGG Opus - formato ideal
        mimetype = 'audio/ogg; codecs=opus';
        filename = 'audio.ogg';
      } else {
        // Opus puro ou formato desconhecido - tentar como OGG Opus
        mimetype = 'audio/ogg; codecs=opus';
        filename = 'audio.ogg';
        console.log('📤 Tentando enviar como OGG Opus (formato pode ser Opus puro)');
      }

      // Enviar áudio via WhatsApp
      // @ts-ignore
      const audioMedia = new MessageMedia(mimetype, audioBase64, filename);
      
      const sentMessage = await this.client.sendMessage(chatId, audioMedia, {
        sendAudioAsVoice: true, // Enviar como nota de voz
      });

      // Enviar para Laravel
      await this.sendToLaravel('messages', {
        instance_name: this.instanceName,
        message_id: sentMessage.id._serialized,
        from: `${this.instanceName}@bot`,
        to: contact,
        message: `[Áudio] ${text}`,
        timestamp: Date.now(),
        direction: 'outgoing',
      });

      console.log(`✅ Áudio enviado para ${chatId}`);
    } catch (error: any) {
      console.error('❌ Erro ao gerar/enviar áudio:', error.message);
      console.error('   Stack:', error.stack);
      
      // Log detalhado para diagnóstico
      if (error.response) {
        console.error('   Resposta da API:', {
          status: error.response.status,
          data: error.response.data,
        });
      }
      
      // Se falhar, enviar como texto
      console.log('📝 Enviando resposta como texto devido ao erro no áudio');
      try {
        await this.sendMessage(contact, text);
      } catch (textError: any) {
        console.error('❌ Erro ao enviar mensagem de texto como fallback:', textError.message);
      }
    }
  }

  private async executeConditionalAction(contact: string, messageText: string, action: any) {
    try {
      const conditions = action.conditions || [];
      const text = messageText.toLowerCase();

      // Verificar cada condição
      for (const condition of conditions) {
        let matches = false;

        // Se for condição padrão (default), pular para verificar depois
        if (condition.default === true) {
          continue;
        }

        // Verificar tipo de condição
        if (condition.type === 'contains' && condition.value) {
          matches = text.includes(condition.value.toLowerCase());
        } else if (condition.type === 'exact' && condition.value) {
          matches = text === condition.value.toLowerCase();
        } else if (condition.type === 'starts_with' && condition.value) {
          matches = text.startsWith(condition.value.toLowerCase());
        } else if (condition.type === 'regex' && condition.value) {
          try {
            const regex = new RegExp(condition.value, 'i');
            matches = regex.test(messageText);
          } catch (e) {
            console.warn('Regex inválida:', condition.value);
          }
        }

        // Se a condição for verdadeira, executar ações correspondentes
        if (matches && condition.actions) {
          console.log(`✅ Condição "${condition.type}: ${condition.value}" verdadeira, executando ações...`);
          for (const subAction of condition.actions) {
            await this.executeAction(contact, messageText, subAction);
          }
          return; // Parar após encontrar primeira condição verdadeira
        }
      }

      // Se nenhuma condição específica foi executada, executar ação padrão
      const defaultCondition = conditions.find((c: any) => c.default === true);
      if (defaultCondition && defaultCondition.actions) {
        console.log('✅ Executando ação padrão (nenhuma condição específica foi verdadeira)');
        for (const subAction of defaultCondition.actions) {
          await this.executeAction(contact, messageText, subAction);
        }
      }
    } catch (error: any) {
      console.error('Erro ao executar ação condicional:', error.message);
    }
  }

  private async executeAction(contact: string, messageText: string, action: any) {
    try {
      if (action.type === 'send_message') {
        // Validar conteúdo antes de tentar enviar
        if (!action.content || typeof action.content !== 'string' || action.content.trim().length === 0) {
          console.warn('⚠️  Ação send_message ignorada: conteúdo vazio');
          return;
        }
        await this.sendMessage(contact, action.content);
      } else if (action.type === 'wait') {
        await new Promise(resolve => setTimeout(resolve, action.duration || 1000));
      } else if (action.type === 'ai_response') {
        await this.sendAIResponse(contact, messageText, action);
      }
    } catch (actionError: any) {
      // Se for erro de mensagem vazia, apenas logar
      if (actionError.message && actionError.message.includes('vazia')) {
        console.warn(`⚠️  Ação ${action.type} ignorada: ${actionError.message}`);
      } else {
        // Para outros erros, propagar
        throw actionError;
      }
    }
  }

  private setupHttpServer() {
    this.httpServer = createServer(async (req, res) => {
      const url = new URL(req.url || '/', `http://${req.headers.host}`);
      const method = req.method;

      // CORS headers
      res.setHeader('Access-Control-Allow-Origin', '*');
      res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
      res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

      if (method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
      }

      // Rota para enviar mensagem
      if (method === 'POST' && url.pathname === '/send-message') {
        let body = '';
        req.on('data', chunk => {
          body += chunk.toString();
        });

        req.on('end', async () => {
          try {
            const data = JSON.parse(body);
            const { contact, message } = data;

            if (!contact || !message) {
              res.writeHead(400, { 'Content-Type': 'application/json' });
              res.end(JSON.stringify({ success: false, error: 'contact e message são obrigatórios' }));
              return;
            }

            // VALIDAÇÃO: Verificar se a mensagem não está vazia
            if (typeof message !== 'string' || message.trim().length === 0) {
              res.writeHead(400, { 'Content-Type': 'application/json' });
              res.end(JSON.stringify({ success: false, error: 'A mensagem não pode estar vazia' }));
              return;
            }

            console.log(`📨 Recebido pedido para enviar mensagem para ${contact}`);

            // Responder imediatamente para evitar timeout
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: true, message: 'Mensagem sendo processada' }));

            // Enviar mensagem em background (não bloqueia a resposta)
            this.sendMessage(contact, message)
              .then(() => {
                console.log(`✅ Mensagem processada com sucesso para ${contact}`);
              })
              .catch((error: any) => {
                console.error(`❌ Erro ao processar mensagem:`, error.message);
              });
          } catch (error: any) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, error: error.message }));
          }
        });
        return;
      }

      // Rota de status
      if (method === 'GET' && url.pathname === '/status') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
          success: true,
          data: {
            instance_name: this.instanceName,
            is_ready: this.isReady,
            status: this.isReady ? 'connected' : 'disconnected',
          },
        }));
        return;
      }

      // Rota não encontrada
      res.writeHead(404, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ success: false, error: 'Rota não encontrada' }));
    });

    this.httpServer.listen(this.botPort, () => {
      console.log(`🌐 Servidor HTTP do bot rodando na porta ${this.botPort}`);
    });
  }

  async start() {
    try {
      console.log('🚀 Iniciando bot WhatsApp...');
      console.log(`📋 Configurações:`);
      console.log(`   Instance Name: ${this.instanceName}`);
      console.log(`   Laravel API URL: ${this.laravelApiUrl}`);
      console.log(`   Bot HTTP Port: ${this.botPort}`);

      // Iniciar servidor HTTP para receber comandos do Laravel
      this.setupHttpServer();

      // Inicializar cliente
      await this.client.initialize();

      console.log('✅ Bot iniciado! Aguardando conexão...');
    } catch (error: any) {
      console.error('❌ Erro ao iniciar bot:', error.message);
      process.exit(1);
    }
  }

  async stop() {
    try {
      if (this.httpServer) {
        this.httpServer.close();
      }
      await this.client.destroy();
      await this.sendToLaravel('bot-status', {
        instance_name: this.instanceName,
        status: 'stopped',
      });
      console.log('🛑 Bot parado');
    } catch (error: any) {
      console.error('Erro ao parar bot:', error.message);
    }
  }
}

// Iniciar bot
const bot = new WhatsAppBot();
bot.start();

// Graceful shutdown
process.on('SIGINT', async () => {
  await bot.stop();
  process.exit(0);
});

process.on('SIGTERM', async () => {
  await bot.stop();
  process.exit(0);
});
