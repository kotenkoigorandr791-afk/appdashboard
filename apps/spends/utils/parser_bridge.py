#!/usr/bin/env python3
"""
Bridge script between PHP and bankstatementparser library
Usage: parser_bridge.py <input_file> <output_file>
"""

import sys
import json
from pathlib import Path
from decimal import Decimal


def serialize_decimal(obj):
    """Convert Decimal to float for JSON serialization"""
    if isinstance(obj, Decimal):
        return float(obj)
    raise TypeError(f"Object of type {type(obj)} is not JSON serializable")


def main():
    if len(sys.argv) != 3:
        print("Usage: parser_bridge.py <input_file> <output_file>", file=sys.stderr)
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    if not Path(input_file).exists():
        print(f"Error: Input file not found: {input_file}", file=sys.stderr)
        sys.exit(1)
    
    try:
        # Import here to give better error messages
        from bankstatementparser.hybrid import smart_ingest
        
        # Use smart_ingest for automatic format detection and parsing
        result = smart_ingest(input_file)
        
        # Convert to dict format
        output = {
            'source_method': result.source_method,
            'source_format': result.source_format,
            'transaction_count': len(result.transactions),
            'transactions': [],
            'verification': None,
            'warnings': list(result.warnings) if result.warnings else [],
            'confidence': None,
        }
        
        # Calculate average confidence for LLM-extracted transactions
        confidences = []
        
        for tx in result.transactions:
            # Use booking_date, fallback to value_date
            tx_date = tx.booking_date or tx.value_date
            tx_dict = {
                'date': tx_date.isoformat() if tx_date else None,
                'amount': float(tx.amount) if tx.amount is not None else 0.0,
                'currency': tx.currency or '',
                'description': tx.description or '',
                'category': tx.category or '',
                'counterparty': tx.counterparty or '',
                'reference': tx.reference or '',
                'transaction_id': tx.transaction_id or '',
                'transaction_hash': tx.transaction_hash or '',
                'confidence': float(tx.confidence) if tx.confidence is not None else None,
                'source_method': tx.source_method or result.source_method,
                'raw_source_text': tx.raw_source_text or '',
            }
            
            output['transactions'].append(tx_dict)
            
            if tx.confidence is not None:
                confidences.append(float(tx.confidence))
        
        # Set average confidence
        if confidences:
            output['confidence'] = sum(confidences) / len(confidences)
        else:
            output['confidence'] = 1.0
        
        # Add verification status if available
        if result.verification:
            v = result.verification
            output['verification'] = {
                'status': v.status.value,
                'opening_balance': float(v.opening_balance) if v.opening_balance is not None else None,
                'closing_balance': float(v.closing_balance) if v.closing_balance is not None else None,
                'total_credits': float(v.total_credits),
                'total_debits': float(v.total_debits),
                'discrepancy': float(v.discrepancy) if v.discrepancy is not None else None,
                'message': v.message or '',
            }
        
        # Write output as JSON
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(output, f, ensure_ascii=False, indent=2, default=serialize_decimal)
        
        print(f"Successfully parsed {len(output['transactions'])} transactions", file=sys.stderr)
        sys.exit(0)
        
    except ImportError as e:
        print(f"Error: bankstatementparser not installed: {e}", file=sys.stderr)
        print("Install with: pip install 'bankstatementparser[hybrid,excel]'", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Error parsing file: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
