#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Versão web do cálculo de empréstimos usando recommend_for_student com acesso ao banco.
Entrada: JSON com {"student_id": int}
Saída: JSON com as opções leve, equilibrada e intensa.
"""

import sys
import json
from standaloneV3 import recommend_for_student, get_student_by_id, get_recommended_loans

def main():
    try:
        # Ler entrada JSON vinda do PHP
        input_data = json.loads(sys.stdin.read())
        student_id = int(input_data.get("student_id", 0))
        profit_threshold = float(input_data.get("profit_threshold", 1.1))
        loan_amount = input_data.get("loan_amount", None)

        # Buscar dados do estudante
        student = get_student_by_id(student_id)
        if not student:
            print(json.dumps({"error": "Estudante não encontrado"}))
            return

        GPA = float(student['gpa']) if student['gpa'] is not None else 3.0
        university = student['universidad'] if student['universidad'] else "Universidade Desconhecida"
        course = student['curso'] if student['curso'] else "Curso Desconhecido"
        salary = student['salario_esperado'] if student['salario_esperado'] else 0
        sex = "male"
        country = student.get('pais', 'BR') or 'BR'

        if loan_amount is None:
            if student['custo_semestre']:
                loan_amount = student['custo_semestre'] * 8
            else:
                print(json.dumps({"error": "Sem custo definido para calcular valor do curso"}))
                return

        # Calcular opções
        options = get_recommended_loans(
            GPA=GPA, sex=sex, university=university, course=course,
            country=country, loan_amount=loan_amount, threshold=profit_threshold,
            salary_base=salary
        )

        # Formatar resultado para o front
        formatted_options = []
        labels = ["Opção equilibrada", "Opção leve", "Opção intensa", "Opção econômica"]
        for i, opt in enumerate(options):
            formatted_options.append({
                "label": labels[i] if i < len(labels) else f"Opção {i+1}",
                "loan_amount": round(opt["loan_amount"], 2),
                "interest_rate": round(opt["juros_anual"], 4),
                "term_years": round(opt["duration_months"] / 12),
                "monthly_payment": round(opt["monthly_payment"], 2),
                "total_payment": round(opt["total_paid"], 2),
                "total_payment_ratio": round(opt["total_paid_ratio"], 2),
                "profit": round(opt["profit"], 2),
                "profit_pct": round(opt["profit_pct"], 2)
            })

        print(json.dumps({"options": formatted_options}))

    except Exception as e:
        print(json.dumps({"error": f"Erro interno: {str(e)}"}))
        sys.exit(1)

if __name__ == "__main__":
    main()
